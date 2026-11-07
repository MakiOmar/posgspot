<?php

namespace App\Services\Storefront\Payment;

use App\Transaction;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;

/**
 * Marks storefront online orders paid in POS.
 */
class StorefrontPaymentRecorder
{
    public function __construct(private TransactionUtil $transactionUtil)
    {
    }

    public function markPaid(Transaction $transaction, int $businessId, array $meta = []): void
    {
        // Webhook and browser return can arrive together; the row lock makes only one record the payment.
        $becamePaid = DB::transaction(function () use ($transaction, $businessId, $meta): bool {
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $alreadyPaid = strtolower(trim((string) $locked->payment_status)) === 'paid';

            if (! $alreadyPaid) {
                $this->transactionUtil->createOrUpdatePaymentLines($locked, [[
                    'amount' => (float) $locked->final_total,
                    'method' => 'card',
                    'payment_line_status' => 'completed',
                ]], $businessId, 1, false);

                // Digital allocate + email is deferred by updatePaymentStatus until after commit.
                $this->transactionUtil->updatePaymentStatus($locked->id, $locked->final_total);
            }

            if (! empty($meta)) {
                $this->storePaymentMeta($locked, $meta);
            }

            return ! $alreadyPaid;
        });

        $transaction->refresh();

        if ($becamePaid && ! empty($transaction->contact_id)) {
            \App\Jobs\SendStorefrontOrderPush::dispatch((int) $transaction->id, 'paid');
        }

        if ($becamePaid) {
            \App\Jobs\NotifyStaffOfStorefrontOrder::dispatch(
                (int) $transaction->id,
                \App\Services\Storefront\StorefrontStaffOrderAlert::PAYMENT_ONLINE
            );
        }
    }

    public function storePaymentMeta(Transaction $transaction, array $meta): void
    {
        $existing = is_array($transaction->storefront_payment_meta)
            ? $transaction->storefront_payment_meta
            : (json_decode((string) $transaction->storefront_payment_meta, true) ?: []);
        $transaction->storefront_payment_meta = array_merge($existing, $meta);
        $transaction->save();
    }
}
