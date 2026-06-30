<?php

namespace App\Mail;

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
        return $this->subject('Order confirmation #'.($this->order['invoice_no'] ?? ''))
            ->view('emails.storefront.order_confirmation');
    }
}
