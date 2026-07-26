<?php

namespace App\Mail;

use App\Contact;
use App\Services\Storefront\StorefrontMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email verification OTP for storefront customers.
 */
class StorefrontEmailVerification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Contact $contact, public string $code)
    {
    }

    public function build()
    {
        $from = app(StorefrontMailService::class)->applyForBusiness((int) $this->contact->business_id);

        return $this->from($from['address'], $from['name'])
            ->subject('Verify your email')
            ->view('emails.storefront.email_verification', [
                'contact' => $this->contact,
                'code' => $this->code,
            ]);
    }
}
