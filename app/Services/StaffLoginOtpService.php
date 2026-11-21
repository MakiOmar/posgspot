<?php

namespace App\Services;

use App\Mail\StaffLoginOtpMail;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Issue and verify 6-digit email OTP for staff web login (/login).
 */
class StaffLoginOtpService
{
    public const PURPOSE = 'staff-login';

    public function __construct(private OneTimeCodeAttempts $codeAttempts)
    {
    }

    /**
     * Send a login OTP when the email matches exactly one eligible staff user.
     * Always returns without revealing whether the email exists.
     */
    public function requestCode(string $email): void
    {
        $email = strtolower(trim($email));
        $users = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('status', 'active')
            ->where('allow_login', 1)
            ->get();

        if ($users->count() !== 1) {
            if ($users->count() > 1) {
                Log::warning('Staff login OTP skipped: email matches multiple users.', [
                    'email' => $email,
                    'count' => $users->count(),
                ]);
            }

            return;
        }

        $user = $users->first();
        if (empty($user->email)) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->login_otp_hash = Hash::make($code);
        $user->login_otp_expires_at = now()->addMinutes(
            max(1, (int) config('auth_otp.expire_minutes', 10))
        );
        $user->save();
        $this->codeAttempts->clear(self::PURPOSE, (string) $user->id);

        try {
            // Sync send — staff login must not depend on a queue worker.
            Mail::to($user->email)->sendNow(new StaffLoginOtpMail($user, $code));
        } catch (\Throwable $e) {
            Log::warning('Staff login OTP email failed.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }
    }

    /**
     * Verify OTP and return the user, or throw a generic validation error.
     */
    public function verifyCode(string $email, string $code): User
    {
        $email = strtolower(trim($email));
        $users = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->get();

        $invalid = ValidationException::withMessages([
            'code' => [__('lang_v1.invalid_or_expired_login_code')],
        ]);

        if ($users->count() !== 1) {
            throw $invalid;
        }

        $user = $users->first();

        if ($user->status !== 'active' || ! $user->allow_login) {
            throw $invalid;
        }

        if (
            empty($user->login_otp_hash)
            || empty($user->login_otp_expires_at)
            || now()->gt($user->login_otp_expires_at)
        ) {
            throw $invalid;
        }

        if (! Hash::check($code, $user->login_otp_hash)) {
            if ($this->codeAttempts->recordFailure(self::PURPOSE, (string) $user->id)) {
                $user->login_otp_hash = null;
                $user->login_otp_expires_at = null;
                $user->save();
            }
            throw $invalid;
        }

        $user->login_otp_hash = null;
        $user->login_otp_expires_at = null;
        $user->save();
        $this->codeAttempts->clear(self::PURPOSE, (string) $user->id);

        return $user;
    }
}
