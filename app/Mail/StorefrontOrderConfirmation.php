<?php

namespace App\Mail;

use App\Services\Storefront\StorefrontMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Order confirmation email sent after storefront checkout.
 */
class StorefrontOrderConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public array $order)
    {
    }

    public function build()
    {
        $businessId = (int) config('storefront.business_id', 1);
        $from = app(StorefrontMailService::class)->applyForBusiness($businessId);

        return $this->from($from['address'], $from['name'])
            ->subject('Order confirmation #'.($this->order['invoice_no'] ?? ''))
            ->view('emails.storefront.order_confirmation');
    }
}
