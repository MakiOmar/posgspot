<?php

namespace App\Mail;

use App\Contact;
use App\Services\Storefront\StorefrontMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Password reset email for storefront customers (6-digit code + in-app link).
 */
class StorefrontPasswordReset extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Contact $contact, public string $token)
    {
    }

    public function build()
    {
        $from = app(StorefrontMailService::class)->applyForBusiness((int) $this->contact->business_id);
        $origin = rtrim((string) config('storefront.url'), '/');
        $query = http_build_query([
            'email' => $this->contact->email,
            'token' => $this->token,
        ]);
        $resetUrl = $origin.'/en/reset-password?'.$query;
        $appUrl = 'gamesspot://reset-password?'.$query;

        return $this->from($from['address'], $from['name'])
            ->subject('Reset your password')
            ->view('emails.storefront.password_reset', [
                'resetUrl' => $resetUrl,
                'appUrl' => $appUrl,
                'code' => $this->token,
            ]);
    }
}
