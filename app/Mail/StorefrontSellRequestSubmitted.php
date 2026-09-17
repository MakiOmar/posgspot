<?php

namespace App\Mail;

use App\Services\Storefront\StorefrontMailService;
use App\StorefrontSellRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Staff notification when a customer submits a Sell to us trade-in request.
 */
class StorefrontSellRequestSubmitted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public StorefrontSellRequest $sellRequest)
    {
    }

    public function build()
    {
        $businessId = (int) $this->sellRequest->business_id;
        $from = app(StorefrontMailService::class)->applyForBusiness($businessId);
        $type = ucfirst((string) $this->sellRequest->type);

        $mailable = $this->from($from['address'], $from['name'])
            ->subject('Sell to us: '.$type.' — '.$this->sellRequest->name)
            ->view('emails.storefront.sell_request');

        $reply = trim((string) ($this->sellRequest->email ?? ''));
        if ($reply !== '' && filter_var($reply, FILTER_VALIDATE_EMAIL)) {
            $mailable->replyTo($reply, (string) $this->sellRequest->name);
        }

        return $mailable;
    }
}
