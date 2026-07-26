<?php

namespace App\Mail;

use App\Contact;
use App\Services\Storefront\StorefrontMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Notify operators that a customer requested account deletion.
 */
class StorefrontAccountDeleteRequest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Contact $contact)
    {
    }

    public function build()
    {
        $from = app(StorefrontMailService::class)->applyForBusiness((int) $this->contact->business_id);

        return $this->from($from['address'], $from['name'])
            ->subject('Storefront account deletion request')
            ->view('emails.storefront.account_delete_request', [
                'contact' => $this->contact,
            ]);
    }
}
