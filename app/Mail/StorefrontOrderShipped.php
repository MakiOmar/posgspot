<?php

namespace App\Mail;

use App\Services\Storefront\StorefrontMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StorefrontOrderShipped extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public array $order)
    {
    }

    public function build()
    {
        $businessId = (int) config('storefront.business_id', 1);
        $from = app(StorefrontMailService::class)->applyForBusiness($businessId);
        $invoice = $this->order['invoice_no'] ?? $this->order['storefront_order_id'] ?? '';

        return $this->from($from['address'], $from['name'])
            ->subject('Your order '.$invoice.' has shipped')
            ->view('emails.storefront.order_shipped')
            ->with(['order' => $this->order]);
    }
}
