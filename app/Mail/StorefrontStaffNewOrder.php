<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the orders inbox about a new website order (sent from NotifyStaffOfStorefrontOrder).
 */
class StorefrontStaffNewOrder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $summary  StorefrontStaffOrderAlert::summary()
     */
    public function __construct(public array $summary, public string $orderUrl)
    {
    }

    public function build()
    {
        return $this->subject(__('lang_v1.storefront_new_order_subject', ['invoice_no' => $this->summary['invoice_no']]))
            ->view('emails.storefront.staff_new_order');
    }
}
