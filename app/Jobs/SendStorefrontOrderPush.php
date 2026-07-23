<?php

namespace App\Jobs;

use App\Services\Storefront\StorefrontPushService;
use App\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Push notification for a storefront order status change.
 */
class SendStorefrontOrderPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $transactionId,
        public string $event = 'shipped'
    ) {
    }

    public function handle(StorefrontPushService $push): void
    {
        $transaction = Transaction::with('contact')->find($this->transactionId);
        if (! $transaction || empty($transaction->contact_id)) {
            return;
        }

        if (($transaction->source ?? '') !== 'storefront' && empty($transaction->storefront_order_id)) {
            return;
        }

        $invoice = (string) ($transaction->invoice_no ?: $transaction->storefront_order_id ?: $transaction->id);
        $title = $this->event === 'paid'
            ? 'Payment received'
            : 'Order shipped';
        $body = $this->event === 'paid'
            ? "Order {$invoice} is paid. Thank you!"
            : "Order {$invoice} is on its way.";

        if (! empty($transaction->shipping_tracking_number) && $this->event === 'shipped') {
            $body .= ' Tracking: '.$transaction->shipping_tracking_number;
        }

        $push->notifyContact((int) $transaction->contact_id, [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'order_'.$this->event,
                'order_id' => (string) $transaction->id,
                'storefront_order_id' => (string) ($transaction->storefront_order_id ?? ''),
            ],
        ]);
    }
}
