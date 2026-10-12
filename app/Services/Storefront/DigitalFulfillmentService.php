<?php

namespace App\Services\Storefront;

use App\Services\Accounts\AccountsAllocationService;
use App\StorefrontDigitalFulfillment;
use App\Transaction;
use App\TransactionSellLine;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Allocates Accounts secrets for paid storefront digital lines and tracks the POS↔Accounts trip.
 */
class DigitalFulfillmentService
{
    public function __construct(
        private AccountsAllocationService $allocator,
        private StorefrontSettingService $settings
    ) {
    }

    /**
     * Create pending ledger rows after checkout (no Accounts call yet).
     *
     * @param  list<array<string, mixed>>  $digitalItems
     */
    public function queuePending(Transaction $transaction, array $digitalItems): void
    {
        $orderId = (string) $transaction->storefront_order_id;
        foreach ($digitalItems as $item) {
            $kind = ($item['kind'] ?? '') === 'card' ? 'card' : 'game';
            $lineKey = (string) ($item['line_key'] ?? $this->defaultLineKey($item, $kind));
            StorefrontDigitalFulfillment::updateOrCreate(
                [
                    'storefront_order_id' => $orderId,
                    'line_key' => $lineKey,
                ],
                [
                    'business_id' => (int) $transaction->business_id,
                    'transaction_id' => (int) $transaction->id,
                    'sell_line_id' => $item['sell_line_id'] ?? null,
                    'kind' => $kind,
                    'status' => 'pending',
                    'request_meta' => $item,
                ]
            );
            $this->repairSellLinePriceFromItem($transaction, $item);
        }

        $this->refreshTransactionTotalsFromSellLines($transaction);
    }

    /**
     * Fix zero-price digital sell lines using catalog price stored on the line item / meta.
     *
     * @param  array<string, mixed>  $item
     */
    private function repairSellLinePriceFromItem(Transaction $transaction, array $item): void
    {
        $price = null;
        foreach ([$item['price'] ?? null, $item['unit_price'] ?? null] as $candidate) {
            if ($candidate !== null && $candidate !== '' && is_numeric($candidate) && (float) $candidate > 0) {
                $price = (float) $candidate;
                break;
            }
        }
        if ($price === null && ! empty($item['kind'])) {
            $price = app(DigitalCatalogService::class)->resolveOfferPrice(
                (int) $transaction->business_id,
                $item
            );
        }
        if ($price === null) {
            return;
        }

        $line = null;
        if (! empty($item['sell_line_id'])) {
            $line = TransactionSellLine::find($item['sell_line_id']);
        }
        if (! $line && ! empty($item['variation_id'])) {
            $line = TransactionSellLine::where('transaction_id', $transaction->id)
                ->where('variation_id', (int) $item['variation_id'])
                ->whereNull('parent_sell_line_id')
                ->first();
        }
        if (! $line) {
            return;
        }
        if ((float) $line->unit_price_inc_tax > 0 && abs((float) $line->unit_price_inc_tax - $price) < 0.0001) {
            return;
        }

        $line->unit_price_before_discount = $price;
        $line->unit_price = $price;
        $line->unit_price_inc_tax = $price;
        $line->item_tax = 0;
        if (! empty($item['title']) && trim((string) $line->sell_line_note) === '') {
            $line->sell_line_note = (string) $item['title'];
        }
        $line->save();
    }

    private function refreshTransactionTotalsFromSellLines(Transaction $transaction): void
    {
        $transaction->load('sell_lines');
        $subtotal = 0.0;
        foreach ($transaction->sell_lines as $line) {
            if (! empty($line->parent_sell_line_id)) {
                continue;
            }
            $subtotal += (float) $line->unit_price_inc_tax * (float) $line->quantity;
        }
        $shipping = (float) $transaction->shipping_charges;
        $discount = (float) ($transaction->discount_amount ?? 0);
        $rp = (float) ($transaction->rp_redeemed_amount ?? 0);
        $final = max(0, round($subtotal + $shipping - $discount - $rp, 4));
        if (abs((float) $transaction->final_total - $final) < 0.0001
            && abs((float) $transaction->total_before_tax - $subtotal) < 0.0001) {
            return;
        }
        $transaction->total_before_tax = round($subtotal, 4);
        $transaction->final_total = $final;
        $transaction->save();
    }

    /**
     * Repair existing digital orders that saved with L.E. 0.00 sell lines.
     */
    public function repairZeroPriceDigitalOrders(?int $transactionId = null): int
    {
        $query = StorefrontDigitalFulfillment::query()->orderBy('id');
        if ($transactionId) {
            $query->where('transaction_id', $transactionId);
        }
        $fixed = 0;
        $seenTx = [];
        foreach ($query->get() as $row) {
            $meta = is_array($row->request_meta) ? $row->request_meta : [];
            $meta['sell_line_id'] = $row->sell_line_id;
            $tx = Transaction::find($row->transaction_id);
            if (! $tx) {
                continue;
            }
            $before = (float) $tx->final_total;
            $this->repairSellLinePriceFromItem($tx, $meta);
            $this->refreshTransactionTotalsFromSellLines($tx);
            $tx->refresh();
            if (abs((float) $tx->final_total - $before) > 0.0001 || ! isset($seenTx[$tx->id])) {
                if ((float) $tx->final_total > $before) {
                    $fixed++;
                }
                $seenTx[$tx->id] = true;
            }
        }

        return $fixed;
    }

    public function fulfillPaidTransaction(Transaction $transaction): int
    {
        $transaction->loadMissing(['contact', 'sell_lines']);
        if (strtolower(trim((string) $transaction->payment_status)) !== 'paid') {
            return 0;
        }

        $rows = StorefrontDigitalFulfillment::where('transaction_id', $transaction->id)
            ->where('storefront_order_id', (string) $transaction->storefront_order_id)
            ->whereIn('status', ['pending', 'failed'])
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $allocated = 0;
        foreach ($rows as $row) {
            $before = $row->status;
            $this->allocateOne($transaction, $row);
            $row->refresh();
            if ($row->status === 'allocated' && $before !== 'allocated') {
                $allocated++;
            }
        }

        return $allocated;
    }

    /**
     * Run after POS payment status becomes paid (edit sell + cash, payment modal, Fawry, etc.).
     */
    public function handleStorefrontBecamePaid(Transaction $transaction): void
    {
        $transaction->refresh();

        // Call-center sales orders use the same ledger (so-*) but must never get storefront mail.
        if (trim((string) $transaction->storefront_order_id) === '') {
            return;
        }

        if (strtolower(trim((string) $transaction->payment_status)) !== 'paid') {
            return;
        }

        $hasPending = StorefrontDigitalFulfillment::where('transaction_id', $transaction->id)
            ->where('storefront_order_id', (string) $transaction->storefront_order_id)
            ->whereIn('status', ['pending', 'failed'])
            ->exists();
        if (! $hasPending) {
            return;
        }

        try {
            $allocated = $this->fulfillPaidTransaction($transaction->fresh(['contact', 'sell_lines']));
            $this->syncStaffNotesFromSecrets($transaction->fresh());
            $this->stampAccountsOrderAsSentToPos($transaction->fresh());
            if ($allocated > 0) {
                app(StorefrontMailService::class)
                    ->sendPaidDigitalConfirmation($transaction->fresh(['contact', 'sell_lines']));
            }
        } catch (\Throwable $e) {
            Log::warning('Storefront digital fulfill after paid failed: '.$e->getMessage(), [
                'transaction_id' => $transaction->id,
                'invoice_no' => $transaction->invoice_no,
            ]);
        }
    }

    public function retryFailed(int $transactionId): void
    {
        $transaction = Transaction::with(['contact', 'sell_lines'])->find($transactionId);
        if (! $transaction) {
            return;
        }
        $this->fulfillPaidTransaction($transaction);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function customerDeliveriesForTransaction(Transaction $transaction): array
    {
        if (strtolower(trim((string) $transaction->payment_status)) !== 'paid') {
            return [];
        }

        if (! $this->exposeCredentialsToCustomer((int) $transaction->business_id)) {
            return [];
        }

        if (! Schema::hasTable('storefront_digital_fulfillments')) {
            return [];
        }

        return StorefrontDigitalFulfillment::where('transaction_id', $transaction->id)
            ->where('status', 'allocated')
            ->get()
            ->map(fn (StorefrontDigitalFulfillment $row) => $row->toCustomerDelivery())
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Whether customers may see allocated secrets (account/email/invoice line note).
     * Staff note always receives credentials regardless of this flag.
     */
    public function exposeCredentialsToCustomer(int $businessId): bool
    {
        $digital = $this->settings->get($businessId)['digital'] ?? [];

        // Default true for backward compatibility when key is absent.
        if (! array_key_exists('expose_credentials_to_customer', $digital)) {
            return true;
        }

        return ! empty($digital['expose_credentials_to_customer']);
    }

    private function allocateOne(Transaction $transaction, StorefrontDigitalFulfillment $row): void
    {
        $digital = $this->settings->get((int) $transaction->business_id)['digital'] ?? [];
        $storeProfileId = (int) ($digital['accounts_store_profile_id'] ?? 17);

        $secrets = $this->allocator->allocate($transaction, $row, $storeProfileId);
        if ($secrets === null) {
            return;
        }

        // Sale line note can print on customer invoices when "show sale description" is on.
        if ($this->exposeCredentialsToCustomer((int) $transaction->business_id)) {
            $this->allocator->appendSecretsToSellLineNote($row->sell_line_id, $secrets);
        }
        // Staff note is always updated (Accounts / POS operators only).
        $staff = $this->allocator->appendSecretsToStaffNote((int) $transaction->id, $row, $secrets);
        if ($staff !== null) {
            $transaction->staff_note = $staff;
        }
        $accountsOrderId = (int) ($row->accounts_order_id ?? 0);
        $this->stampAccountsOrderAsSentToPos(
            $transaction,
            $accountsOrderId > 0 ? $accountsOrderId : null
        );
    }

    /**
     * Mark Accounts order(s) as sent to POS (sets pos_order_id → green POS badge).
     *
     * Prefer a single allocate call that already sets pos_order_id; this stamp is the safety net.
     */
    public function stampAccountsOrderAsSentToPos(Transaction $transaction, ?int $accountsOrderId = null): bool
    {
        $storefrontOrderId = trim((string) ($transaction->storefront_order_id ?? ''));

        if ($accountsOrderId === null || $accountsOrderId <= 0) {
            $accountsOrderId = (int) StorefrontDigitalFulfillment::query()
                ->where('transaction_id', $transaction->id)
                ->whereNotNull('accounts_order_id')
                ->value('accounts_order_id');
            if ($accountsOrderId <= 0) {
                $accountsOrderId = null;
            }
        }

        if ($storefrontOrderId === '' && ($accountsOrderId === null || $accountsOrderId <= 0) && empty($transaction->id)) {
            return false;
        }

        return $this->allocator->stampPosOrder($storefrontOrderId, (int) $transaction->id, $accountsOrderId);
    }

    /**
     * Backfill Staff note credentials from already-allocated secrets (N/A placeholders).
     */
    public function syncStaffNotesFromSecrets(Transaction $transaction): int
    {
        $rows = StorefrontDigitalFulfillment::where('transaction_id', $transaction->id)
            ->where('status', 'allocated')
            ->orderBy('id')
            ->get();

        $updated = 0;
        foreach ($rows as $row) {
            $secrets = $row->readSecrets();
            if (! is_array($secrets) || $secrets === []) {
                continue;
            }
            $before = (string) (Transaction::where('id', $transaction->id)->value('staff_note') ?? '');
            $after = (string) ($this->allocator->appendSecretsToStaffNote((int) $transaction->id, $row, $secrets) ?? $before);
            if ($after !== $before) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function defaultLineKey(array $item, string $kind): string
    {
        if ($kind === 'card') {
            return 'card|category:'.($item['card_category_id'] ?? '0');
        }

        return 'ps'.($item['platform'] ?? '4').'_'.($item['type'] ?? 'primary').'_stock|game:'.($item['game_id'] ?? '0');
    }
}
