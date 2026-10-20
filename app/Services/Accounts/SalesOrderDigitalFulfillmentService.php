<?php

namespace App\Services\Accounts;

use App\Services\AccountsCatalogService;
use App\Services\Storefront\StorefrontSettingService;
use App\StorefrontDigitalFulfillment;
use App\Transaction;
use App\TransactionSellLine;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Allocates Accounts game accounts / cards for call-center sales orders and, when
 * ACCOUNTS_POS_SELL is on, final POS / direct sells.
 *
 * Lines staff tick "Fetch account now" are allocated on save. On sales orders everything else
 * follows once fully paid; POS sells never fetch unticked lines. One ledger row per unit (`so-{id}` / `pos-{id}` + per-line keys)
 * keeps every trigger idempotent, and Accounts itself dedupes on (storefront_order_id, line_key).
 */
class SalesOrderDigitalFulfillmentService
{
    public const ORDER_PREFIX = 'so-';

    public const POS_SELL_PREFIX = 'pos-';

    /** A row stuck in "processing" this long is assumed abandoned (crashed request). */
    private const STALE_PROCESSING_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private AccountsAllocationService $allocator,
        private StorefrontSettingService $settings
    ) {
    }

    public static function orderRef(int $salesOrderId): string
    {
        return self::ORDER_PREFIX.$salesOrderId;
    }

    /** Ledger order id: `so-{id}` for sales orders, `pos-{id}` for POS / direct sells. */
    public static function refFor(Transaction $transaction): string
    {
        return ($transaction->type === 'sales_order' ? self::ORDER_PREFIX : self::POS_SELL_PREFIX).$transaction->id;
    }

    /** @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query */
    private static function whereOwnPrefix($query): void
    {
        $query->where(function ($q) {
            $q->where('storefront_order_id', 'like', self::ORDER_PREFIX.'%')
                ->orWhere('storefront_order_id', 'like', self::POS_SELL_PREFIX.'%');
        });
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.accounts.sales_order_fulfillment', true)
            && Schema::hasTable('storefront_digital_fulfillments');
    }

    /** Storefront and Accounts-originated orders already have their own fulfilment path. */
    public function isEligible(Transaction $transaction): bool
    {
        if ($transaction->custom_field_1 === 'account_order' || $transaction->source === 'storefront') {
            return false;
        }
        if ($transaction->type === 'sales_order') {
            return true;
        }

        // Drafts, quotations and suspended sales wait until they are finalised.
        return $transaction->type === 'sell'
            && $transaction->status === 'final'
            && empty($transaction->is_suspend)
            && AccountsCatalogService::posSellEnabled();
    }

    /**
     * Allocate the given sales-order lines now, whatever the payment status.
     *
     * @param  list<int>  $sellLineIds
     * @return array{allocated: int, failed: int, errors: list<string>}
     */
    public function fulfilLines(Transaction $salesOrder, array $sellLineIds): array
    {
        $result = ['allocated' => 0, 'failed' => 0, 'errors' => []];
        $sellLineIds = array_values(array_unique(array_filter(array_map('intval', $sellLineIds))));
        if ($sellLineIds === [] || ! $this->isEnabled() || ! $this->isEligible($salesOrder)) {
            return $result;
        }

        try {
            $this->queue($salesOrder, $sellLineIds, 'on_save');

            return $this->allocatePending($salesOrder, $sellLineIds);
        } catch (\Throwable $e) {
            Log::error('Sales order account allocation (on save) failed: '.$e->getMessage(), [
                'transaction_id' => $salesOrder->id,
            ]);
            $result['errors'][] = __('lang_v1.accounts_allocation_unavailable');

            return $result;
        }
    }

    /**
     * POS / direct sells only fetch the lines staff ticked; sales orders also fetch the rest once paid.
     */
    public static function fetchesRemainingWhenPaid(Transaction $transaction): bool
    {
        return $transaction->type === 'sales_order';
    }

    /**
     * Allocate every remaining account line once the sales order is fully paid.
     *
     * @return array{allocated: int, failed: int, errors: list<string>}
     */
    public function handlePaid(Transaction $salesOrder): array
    {
        $result = ['allocated' => 0, 'failed' => 0, 'errors' => []];
        $salesOrder->refresh();
        if (! self::fetchesRemainingWhenPaid($salesOrder)) {
            return $result;
        }
        if (! $this->isEnabled() || ! $this->isEligible($salesOrder) || ! $this->isPaid($salesOrder)) {
            return $result;
        }

        try {
            $this->queue($salesOrder, null, 'on_paid');

            return $this->allocatePending($salesOrder, null);
        } catch (\Throwable $e) {
            Log::error('Sales order account allocation (paid) failed: '.$e->getMessage(), [
                'transaction_id' => $salesOrder->id,
            ]);

            return $result;
        }
    }

    /**
     * Retry failed / abandoned rows: on-save rows always, paid-trigger rows only once paid.
     */
    public function retry(?int $salesOrderId = null): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $query = StorefrontDigitalFulfillment::query()
            ->tap(fn ($q) => self::whereOwnPrefix($q))
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->where(function ($q) {
                $q->whereIn('status', ['pending', 'failed'])
                    ->orWhere(function ($p) {
                        $p->where('status', 'processing')
                            ->where('updated_at', '<', now()->subMinutes(self::STALE_PROCESSING_MINUTES));
                    });
            });
        if ($salesOrderId) {
            $query->where('transaction_id', $salesOrderId);
        }

        $allocated = 0;
        foreach ($query->distinct()->pluck('transaction_id') as $transactionId) {
            $salesOrder = Transaction::with('contact')->find($transactionId);
            if (! $salesOrder || ! $this->isEligible($salesOrder)) {
                continue;
            }
            $lineIds = self::fetchesRemainingWhenPaid($salesOrder) && $this->isPaid($salesOrder)
                ? null
                : $this->onSaveLineIds($salesOrder);
            if ($lineIds === []) {
                continue;
            }
            $allocated += $this->allocatePending($salesOrder, $lineIds)['allocated'];
        }

        return $allocated;
    }

    /**
     * What the cashier hands over after a POS sell: allocated credentials plus how many
     * units are still waiting (failed / unpaid, picked up by the retry command).
     *
     * @return array{deliveries: list<array<string, mixed>>, waiting: int}
     */
    public function saleDeliverySummary(Transaction $transaction): array
    {
        $summary = ['deliveries' => [], 'waiting' => 0];
        if (! $this->isEnabled() || ! $this->isEligible($transaction)) {
            return $summary;
        }

        $rows = StorefrontDigitalFulfillment::query()
            ->where('storefront_order_id', self::refFor($transaction))
            ->orderBy('id')
            ->get();
        foreach ($rows as $row) {
            $delivery = $row->toCustomerDelivery();
            if ($delivery !== null) {
                $summary['deliveries'][] = $delivery;
            } else {
                $summary['waiting']++;
            }
        }

        return $summary;
    }

    /**
     * Allocated units per sell line of a sales order or POS sell (locks qty / remove on edit).
     *
     * @return array<int, int>
     */
    public function allocatedUnitsBySellLine(int $transactionId): array
    {
        if (! Schema::hasTable('storefront_digital_fulfillments')) {
            return [];
        }

        return StorefrontDigitalFulfillment::query()
            ->where('transaction_id', $transactionId)
            ->tap(fn ($q) => self::whereOwnPrefix($q))
            ->where('status', 'allocated')
            ->whereNotNull('sell_line_id')
            ->groupBy('sell_line_id')
            ->selectRaw('sell_line_id, COUNT(*) as units')
            ->pluck('units', 'sell_line_id')
            ->map(fn ($units) => (int) $units)
            ->all();
    }

    /**
     * One ledger row per unit for the sales order's account lines (or only the given lines).
     * Drops unallocated rows for removed lines / reduced quantities.
     *
     * @param  list<int>|null  $sellLineIds
     */
    private function queue(Transaction $salesOrder, ?array $sellLineIds, string $trigger): void
    {
        $ref = self::refFor($salesOrder);
        $lines = TransactionSellLine::query()
            ->join('products as p', 'p.id', '=', 'transaction_sell_lines.product_id')
            ->where('transaction_sell_lines.transaction_id', $salesOrder->id)
            ->whereNull('transaction_sell_lines.parent_sell_line_id')
            ->select('transaction_sell_lines.*', 'p.sku as product_sku', 'p.name as product_name')
            ->get();

        $keep = [];
        foreach ($lines as $line) {
            if (! empty($line->so_line_id)) {
                // Invoice line copied from a sales order: the account is allocated on the order.
                continue;
            }
            $digital = AccountsCatalogService::digitalFromSku(
                $line->product_sku,
                $line->product_name,
                (float) $line->unit_price_inc_tax
            );
            if ($digital === null) {
                continue;
            }
            $units = max(0, (int) round((float) $line->quantity));
            for ($n = 1; $n <= $units; $n++) {
                $lineKey = $digital['line_key'].'|line:'.$line->id.'|n:'.$n;
                $keep[] = $lineKey;
                if ($sellLineIds !== null && ! in_array((int) $line->id, $sellLineIds, true)) {
                    continue;
                }
                StorefrontDigitalFulfillment::firstOrCreate(
                    ['storefront_order_id' => $ref, 'line_key' => $lineKey],
                    [
                        'business_id' => (int) $salesOrder->business_id,
                        'transaction_id' => (int) $salesOrder->id,
                        'sell_line_id' => (int) $line->id,
                        'kind' => $digital['kind'],
                        'status' => 'pending',
                        'request_meta' => array_merge($digital, ['trigger' => $trigger]),
                    ]
                );
            }
        }

        StorefrontDigitalFulfillment::query()
            ->where('storefront_order_id', $ref)
            ->whereIn('status', ['pending', 'failed'])
            ->when($keep !== [], fn ($q) => $q->whereNotIn('line_key', $keep))
            ->delete();
    }

    /**
     * @param  list<int>|null  $sellLineIds
     * @return array{allocated: int, failed: int, errors: list<string>}
     */
    private function allocatePending(Transaction $salesOrder, ?array $sellLineIds): array
    {
        $result = ['allocated' => 0, 'failed' => 0, 'errors' => []];
        $ref = self::refFor($salesOrder);
        $rowIds = StorefrontDigitalFulfillment::query()
            ->where('storefront_order_id', $ref)
            ->where(function ($q) {
                $q->whereIn('status', ['pending', 'failed'])
                    ->orWhere(function ($p) {
                        $p->where('status', 'processing')
                            ->where('updated_at', '<', now()->subMinutes(self::STALE_PROCESSING_MINUTES));
                    });
            })
            ->when($sellLineIds !== null, fn ($q) => $q->whereIn('sell_line_id', $sellLineIds))
            ->orderBy('id')
            ->pluck('id');

        if ($rowIds->isEmpty()) {
            return $result;
        }

        $salesOrder->loadMissing('contact');
        $storeProfileId = $this->storeProfileId((int) $salesOrder->business_id);
        $exposeOnLine = $this->exposeCredentialsOnLine((int) $salesOrder->business_id);
        $extra = ['notes' => $this->accountsNote($salesOrder)];

        foreach ($rowIds as $rowId) {
            $row = $this->claim((int) $rowId);
            if (! $row) {
                continue;
            }

            try {
                $secrets = $this->allocator->allocate($salesOrder, $row, $storeProfileId, $extra);
            } catch (\Throwable $e) {
                $row->status = 'failed';
                $row->last_error = mb_substr($e->getMessage(), 0, 500);
                $row->save();
                $secrets = null;
            }

            if ($secrets === null && $row->status === 'pending') {
                // Supplier order in progress: counted as "waiting" until the retry command picks it up.
                continue;
            }
            if ($secrets === null) {
                $result['failed']++;
                $error = $this->failureLine($row);
                $result['errors'][] = $error;
                $this->allocator->appendStaffNoteLine((int) $salesOrder->id, $error);

                continue;
            }

            $result['allocated']++;
            $this->writeNotes($salesOrder, $row, $secrets, $exposeOnLine);
        }

        return $result;
    }

    /** Lock the row, re-check it is still allocatable, and mark it processing. */
    private function claim(int $rowId): ?StorefrontDigitalFulfillment
    {
        return DB::transaction(function () use ($rowId) {
            $row = StorefrontDigitalFulfillment::whereKey($rowId)->lockForUpdate()->first();
            if (! $row) {
                return null;
            }
            $stale = $row->status === 'processing'
                && $row->updated_at !== null
                && $row->updated_at->lt(now()->subMinutes(self::STALE_PROCESSING_MINUTES));
            if (! in_array($row->status, ['pending', 'failed'], true) && ! $stale) {
                return null;
            }
            $row->status = 'processing';
            $row->save();

            return $row;
        });
    }

    /**
     * Credentials go on the sales order and on any invoice already converted from it.
     *
     * @param  array<string, mixed>  $secrets
     */
    private function writeNotes(Transaction $salesOrder, StorefrontDigitalFulfillment $row, array $secrets, bool $exposeOnLine): void
    {
        $this->allocator->appendSecretsToStaffNote((int) $salesOrder->id, $row, $secrets);
        if ($exposeOnLine) {
            $this->allocator->appendSecretsToSellLineNote($row->sell_line_id, $secrets);
        }

        $convertedLines = TransactionSellLine::query()
            ->where('so_line_id', $row->sell_line_id)
            ->get(['id', 'transaction_id']);
        foreach ($convertedLines as $converted) {
            $this->allocator->appendSecretsToStaffNote((int) $converted->transaction_id, $row, $secrets);
            if ($exposeOnLine) {
                $this->allocator->appendSecretsToSellLineNote((int) $converted->id, $secrets);
            }
        }
    }

    /**
     * @return list<int>
     */
    private function onSaveLineIds(Transaction $salesOrder): array
    {
        return StorefrontDigitalFulfillment::query()
            ->where('storefront_order_id', self::refFor($salesOrder))
            ->get(['sell_line_id', 'request_meta'])
            ->filter(fn ($row) => ($row->request_meta['trigger'] ?? null) === 'on_save')
            ->pluck('sell_line_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function failureLine(StorefrontDigitalFulfillment $row): string
    {
        $title = (string) ($row->request_meta['title'] ?? $row->line_key);

        return __('lang_v1.account_allocation_failed_note', [
            'title' => $title,
            'reason' => mb_substr((string) ($row->last_error ?? ''), 0, 200),
        ]);
    }

    private function accountsNote(Transaction $salesOrder): string
    {
        $user = User::find($salesOrder->created_by);
        $agent = $user ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) : '';

        $label = $salesOrder->type === 'sales_order' ? 'POS sales order ' : 'POS sale ';

        return mb_substr(trim($label.$salesOrder->invoice_no.($agent !== '' ? ' / '.$agent : '')), 0, 500);
    }

    private function storeProfileId(int $businessId): int
    {
        $configured = config('services.accounts.sales_order_store_profile_id');
        if (! empty($configured)) {
            return (int) $configured;
        }
        $digital = $this->settings->get($businessId)['digital'] ?? [];

        return (int) ($digital['accounts_store_profile_id'] ?? 17);
    }

    private function exposeCredentialsOnLine(int $businessId): bool
    {
        $digital = $this->settings->get($businessId)['digital'] ?? [];

        return ! array_key_exists('expose_credentials_to_customer', $digital)
            || ! empty($digital['expose_credentials_to_customer']);
    }

    private function isPaid(Transaction $transaction): bool
    {
        return strtolower(trim((string) $transaction->payment_status)) === 'paid';
    }
}
