<?php

namespace App\Services\Storefront\Payment;

use App\Transaction;
use App\Utils\TransactionUtil;

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
        $alreadyPaid = strtolower(trim((string) $transaction->payment_status)) === 'paid';

        if (! $alreadyPaid) {
            $this->transactionUtil->createOrUpdatePaymentLines($transaction, [[
                'amount' => (float) $transaction->final_total,
                'method' => 'card',
                'payment_line_status' => 'completed',
            ]], $businessId, 1, false);

            $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);
        }

        if (! empty($meta)) {
            $existing = is_array($transaction->storefront_payment_meta)
                ? $transaction->storefront_payment_meta
                : (json_decode((string) $transaction->storefront_payment_meta, true) ?: []);
            $transaction->storefront_payment_meta = array_merge($existing, $meta);
            $transaction->save();
        }

        $transaction->refresh();
        if (strtolower(trim((string) $transaction->payment_status)) === 'paid') {
            try {
                app(\App\Services\Storefront\DigitalFulfillmentService::class)
                    ->fulfillPaidTransaction($transaction);
                app(\App\Services\Storefront\StorefrontMailService::class)
                    ->sendPaidDigitalConfirmation($transaction->fresh(['contact', 'sell_lines']));
            } catch (\Throwable $e) {
                \Log::warning('Storefront digital fulfill/email after paid failed: '.$e->getMessage());
            }
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
