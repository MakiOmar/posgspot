<?php

namespace App\Jobs;

use App\Mail\StorefrontStaffNewOrder;
use App\Notifications\StorefrontNewOrderNotification;
use App\Services\Storefront\StorefrontMailService;
use App\Services\Storefront\StorefrontStaffOrderAlert;
use App\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Alerts staff once about a new website order: POS bell for branch staff, then the orders inbox email.
 */
class NotifyStaffOfStorefrontOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $transactionId, public string $payment)
    {
        $this->afterCommit();
    }

    public function handle(StorefrontStaffOrderAlert $alert, StorefrontMailService $mail): void
    {
        $transaction = Transaction::find($this->transactionId);
        if (! $transaction || ($transaction->source ?? '') !== 'storefront' || ! $alert->claim($transaction)) {
            return;
        }

        $summary = $alert->summary($transaction, $this->payment);
        $context = ['transaction_id' => $transaction->id];

        try {
            Notification::send($alert->recipients($transaction), new StorefrontNewOrderNotification($summary));
        } catch (\Throwable $e) {
            Log::warning('Website order bell alert failed: '.$e->getMessage(), $context);
        }

        try {
            $businessId = (int) $transaction->business_id;
            $orderUrl = action([\App\Http\Controllers\SellController::class, 'index'], ['view_sell' => $transaction->id]);
            $mail->sendNowForBusiness($businessId, $mail->ordersRecipient($businessId), new StorefrontStaffNewOrder($summary, $orderUrl));
        } catch (\Throwable $e) {
            Log::warning('Website order email alert failed: '.$e->getMessage(), $context);
        }
    }
}
