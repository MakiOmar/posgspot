<?php

namespace App\Services\Storefront;

use App\CouponRedemption;
use App\Services\Accounts\SalesOrderDigitalFulfillmentService;
use App\StorefrontDigitalFulfillment;
use App\StorefrontSetting;
use App\Transaction;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Deletes abandoned unpaid online storefront checkouts after a configurable wait.
 * COD and paid/alerted orders are never candidates.
 */
class StorefrontUnpaidOrderPruner
{
    public function __construct(
        private StorefrontSettingService $settings,
        private TransactionUtil $transactionUtil,
        private SalesOrderDigitalFulfillmentService $digitalFulfillment,
    ) {}

    /**
     * Waiting hours from storefront settings (0 = auto-delete disabled).
     */
    public function waitingHours(int $businessId): int
    {
        $hours = (int) ($this->settings->get($businessId)['orders']['unpaid_abandon_after_hours'] ?? 0);

        return max(0, $hours);
    }

    /**
     * @return list<int>
     */
    public function businessIdsToScan(?int $onlyBusinessId = null): array
    {
        if ($onlyBusinessId !== null && $onlyBusinessId > 0) {
            return [$onlyBusinessId];
        }

        $configured = (int) config('storefront.business_id', 0);
        $ids = StorefrontSetting::query()->orderBy('business_id')->pluck('business_id')->map(fn ($id) => (int) $id)->all();
        if ($configured > 0 && ! in_array($configured, $ids, true)) {
            $ids[] = $configured;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @return array{candidates: int, deleted: int, skipped: int, ids: list<int>}
     */
    public function prune(int $businessId, int $afterHours, int $limit, bool $dryRun = false): array
    {
        $result = [
            'candidates' => 0,
            'deleted' => 0,
            'skipped' => 0,
            'ids' => [],
        ];

        if ($afterHours <= 0 || $limit <= 0) {
            return $result;
        }

        $cutoff = now()->subHours($afterHours);
        $candidates = $this->candidateQuery($businessId, $cutoff)
            ->limit($limit)
            ->get(['id', 'business_id', 'invoice_no', 'storefront_order_id', 'created_at']);

        $result['candidates'] = $candidates->count();
        $result['ids'] = $candidates->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($dryRun || $candidates->isEmpty()) {
            return $result;
        }

        foreach ($candidates as $row) {
            $deleted = $this->deleteOne($businessId, (int) $row->id);
            if ($deleted) {
                $result['deleted']++;
            } else {
                $result['skipped']++;
            }
        }

        return $result;
    }

    /**
     * Unpaid online website checkouts that never reached staff (no COD, no paid alert).
     *
     * @return \Illuminate\Database\Eloquent\Builder<Transaction>
     */
    public function candidateQuery(int $businessId, $cutoff)
    {
        return Transaction::query()
            ->where('business_id', $businessId)
            ->where('type', 'sell')
            ->where('source', 'storefront')
            ->whereNotNull('storefront_order_id')
            ->where('storefront_order_id', '!=', '')
            ->whereNull('storefront_staff_notified_at')
            ->whereIn('payment_status', ['due', 'failed'])
            ->where('created_at', '<', $cutoff)
            ->whereHas('payment_lines', function ($q) {
                $q->where('method', 'card')
                    ->where('payment_line_status', 'pending');
            })
            ->orderBy('id');
    }

    private function deleteOne(int $businessId, int $transactionId): bool
    {
        try {
            return DB::transaction(function () use ($businessId, $transactionId) {
                $tx = Transaction::query()
                    ->where('business_id', $businessId)
                    ->whereKey($transactionId)
                    ->lockForUpdate()
                    ->first();

                if (! $tx || $tx->source !== 'storefront' || $tx->storefront_staff_notified_at !== null) {
                    return false;
                }

                $status = strtolower(trim((string) $tx->payment_status));
                if (! in_array($status, ['due', 'failed'], true)) {
                    return false;
                }

                // Never remove a sale that already handed out Accounts credentials.
                if (! empty($this->digitalFulfillment->allocatedUnitsBySellLine($transactionId))) {
                    Log::warning('Storefront unpaid prune skipped: allocated digital credentials', [
                        'transaction_id' => $transactionId,
                    ]);

                    return false;
                }

                if (Schema::hasTable('storefront_digital_fulfillments')) {
                    StorefrontDigitalFulfillment::query()
                        ->where('transaction_id', $transactionId)
                        ->delete();
                }

                if (Schema::hasTable('coupon_redemptions')) {
                    CouponRedemption::query()
                        ->where('transaction_id', $transactionId)
                        ->delete();
                }

                $output = $this->transactionUtil->deleteSale($businessId, $transactionId);
                if (empty($output['success'])) {
                    Log::warning('Storefront unpaid prune deleteSale failed', [
                        'transaction_id' => $transactionId,
                        'msg' => $output['msg'] ?? null,
                    ]);

                    return false;
                }

                Log::info('Storefront unpaid order pruned', [
                    'transaction_id' => $transactionId,
                    'invoice_no' => $tx->invoice_no,
                    'storefront_order_id' => $tx->storefront_order_id,
                ]);

                return true;
            });
        } catch (\Throwable $e) {
            Log::error('Storefront unpaid prune failed: '.$e->getMessage(), [
                'transaction_id' => $transactionId,
            ]);

            return false;
        }
    }
}
