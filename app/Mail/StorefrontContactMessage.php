<?php

namespace App\Mail;

use App\Services\Storefront\StorefrontMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Contact form submission from the public storefront.
 */
class StorefrontContactMessage extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public array $submission)
    {
    }

    public function build()
    {
        $businessId = (int) config('storefront.business_id', 1);
        $from = app(StorefrontMailService::class)->applyForBusiness($businessId);

        return $this->from($from['address'], $from['name'])
            ->replyTo($this->submission['email'], $this->submission['name'])
            ->subject('Storefront contact: '.$this->submission['name'])
            ->view('emails.storefront.contact_message');
    }
}
