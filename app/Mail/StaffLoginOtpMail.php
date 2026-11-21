<?php

namespace App\Mail;

use App\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Staff web login OTP (sent synchronously via sendNow).
 */
class StaffLoginOtpMail extends Mailable
{
    use SerializesModels;

    public function __construct(public User $user, public string $code)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('lang_v1.your_login_code'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.staff-login-otp',
            with: [
                'user' => $this->user,
                'code' => $this->code,
                'expireMinutes' => max(1, (int) config('auth_otp.expire_minutes', 10)),
            ],
        );
    }
}
