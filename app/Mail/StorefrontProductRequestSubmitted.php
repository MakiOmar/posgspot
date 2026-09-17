<?php

namespace App\Mail;

use App\Services\Storefront\StorefrontMailService;
use App\StorefrontProductRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Staff notification when a customer submits a product request.
 */
class StorefrontProductRequestSubmitted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public StorefrontProductRequest $productRequest)
    {
    }

    public function build()
    {
        $businessId = (int) $this->productRequest->business_id;
        $from = app(StorefrontMailService::class)->applyForBusiness($businessId);

        $mailable = $this->from($from['address'], $from['name'])
            ->subject('Product request: '.$this->productRequest->product_name.' — '.$this->productRequest->name)
            ->view('emails.storefront.product_request');

        $reply = trim((string) ($this->productRequest->email ?? ''));
        if ($reply !== '' && filter_var($reply, FILTER_VALIDATE_EMAIL)) {
            $mailable->replyTo($reply, (string) $this->productRequest->name);
        }

        return $mailable;
    }
}
