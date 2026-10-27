<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Test message for a storefront notification inbox (Storefront settings → Contact).
 */
class StorefrontTestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $type, public string $businessName, public string $recipient)
    {
    }

    public function build()
    {
        $typeLabel = __('lang_v1.storefront_notify_type_'.$this->type);

        return $this->subject(__('lang_v1.storefront_test_email_subject', ['type' => $typeLabel, 'business' => $this->businessName]))
            ->view('emails.storefront.test_email', [
                'typeLabel' => $typeLabel,
                'sentAt' => now()->toDateTimeString(),
            ]);
    }
}
