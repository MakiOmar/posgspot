<?php

namespace App\Services;

use App\Mail\StaffLoginOtpMail;
use App\User;
use Illuminate\Support\Facades\Cache;
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

    public const RESULT_SENT = 'sent';

    public const RESULT_NOT_FOUND = 'not_found';

    public const RESULT_INACTIVE = 'inactive';

    public const RESULT_LOGIN_DISABLED = 'login_disabled';

    public const RESULT_AMBIGUOUS = 'ambiguous';

    public const RESULT_MAIL_FAILED = 'mail_failed';

    public const RESULT_COOLDOWN = 'cooldown';

    public function __construct(private OneTimeCodeAttempts $codeAttempts)
    {
    }

    /**
     * Send a login OTP when the address matches exactly one eligible staff user.
     *
     * Within the resend cooldown, returns RESULT_COOLDOWN (no mail). After cooldown,
     * a still-valid code is re-mailed unchanged; otherwise a new code is issued.
     *
     * @return array{ok: bool, reason: string, retry_after?: int, reused?: bool}
     */
    public function requestCode(string $email): array
    {
        $email = strtolower(trim($email));
        $users = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->get();

        if ($users->isEmpty()) {
            return ['ok' => false, 'reason' => self::RESULT_NOT_FOUND];
        }

        if ($users->count() > 1) {
            Log::warning('Staff login OTP skipped: email matches multiple users.', [
                'email' => $email,
                'count' => $users->count(),
            ]);

            return ['ok' => false, 'reason' => self::RESULT_AMBIGUOUS];
        }

        $user = $users->first();
        if (empty($user->email)) {
            return ['ok' => false, 'reason' => self::RESULT_NOT_FOUND];
        }

        if ($user->status !== 'active') {
            return ['ok' => false, 'reason' => self::RESULT_INACTIVE];
        }

        if (! $user->allow_login) {
            return ['ok' => false, 'reason' => self::RESULT_LOGIN_DISABLED];
        }

        $retryAfter = $this->secondsUntilResendAllowed($user);
        if ($retryAfter > 0) {
            return [
                'ok' => false,
                'reason' => self::RESULT_COOLDOWN,
                'retry_after' => $retryAfter,
            ];
        }

        $payload = $this->cachedPayload($user);
        $reuse = is_array($payload)
            && $this->hasActiveOtp($user)
            && is_string($payload['code'] ?? null)
            && preg_match('/^\d{6}$/', $payload['code']);

        if ($reuse) {
            $code = $payload['code'];
            $this->rememberPayload($user, $code, (int) ($user->login_otp_expires_at?->getTimestamp() ?? now()->addMinutes($this->expireMinutes())->getTimestamp()));

            return $this->sendMail($user, $code, reused: true);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes($this->expireMinutes());
        $user->login_otp_hash = Hash::make($code);
        $user->login_otp_expires_at = $expiresAt;
        $user->save();
        $this->codeAttempts->clear(self::PURPOSE, (string) $user->id);
        $this->rememberPayload($user, $code, $expiresAt->getTimestamp());

        return $this->sendMail($user, $code, reused: false);
    }

    /**
     * Seconds until another OTP email may be sent for this address (0 = allowed now).
     */
    public function secondsUntilResendAllowedForEmail(string $email): int
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return 0;
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        return $user ? $this->secondsUntilResendAllowed($user) : 0;
    }

    /**
     * Human-readable failure message for a requestCode() reason.
     */
    public static function failureMessage(string $reason, ?int $retryAfter = null): string
    {
        return match ($reason) {
            self::RESULT_NOT_FOUND => __('lang_v1.login_otp_email_not_found'),
            self::RESULT_INACTIVE => __('lang_v1.user_inactive'),
            self::RESULT_LOGIN_DISABLED => __('lang_v1.login_not_allowed'),
            self::RESULT_AMBIGUOUS => __('lang_v1.login_otp_email_ambiguous'),
            self::RESULT_MAIL_FAILED => __('lang_v1.login_otp_mail_failed'),
            self::RESULT_COOLDOWN => __('lang_v1.login_otp_resend_wait', [
                'seconds' => max(1, (int) $retryAfter),
            ]),
            default => __('lang_v1.login_otp_mail_failed'),
        };
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
                $this->clearOtp($user);
            }
            throw $invalid;
        }

        $this->clearOtp($user);
        $this->codeAttempts->clear(self::PURPOSE, (string) $user->id);

        return $user;
    }

    private function sendMail(User $user, string $code, bool $reused): array
    {
        try {
            // Sync send — staff login must not depend on a queue worker.
            Mail::to($user->email)->sendNow(new StaffLoginOtpMail($user, $code));
        } catch (\Throwable $e) {
            Log::warning('Staff login OTP email failed.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            report($e);

            if (! $reused) {
                $this->clearOtp($user);
            } else {
                // Keep the active hash; only drop the send timestamp so cooldown can restart later.
                $this->forgetCache($user);
            }

            return ['ok' => false, 'reason' => self::RESULT_MAIL_FAILED];
        }

        return ['ok' => true, 'reason' => self::RESULT_SENT, 'reused' => $reused];
    }

    private function secondsUntilResendAllowed(User $user): int
    {
        $payload = $this->cachedPayload($user);
        if (! is_array($payload) || ! isset($payload['sent_at'])) {
            return 0;
        }

        $cooldown = max(1, (int) config('auth_otp.resend_cooldown_seconds', 60));
        $elapsed = now()->getTimestamp() - (int) $payload['sent_at'];

        return max(0, $cooldown - $elapsed);
    }

    private function hasActiveOtp(User $user): bool
    {
        return ! empty($user->login_otp_hash)
            && ! empty($user->login_otp_expires_at)
            && now()->lte($user->login_otp_expires_at);
    }

    private function expireMinutes(): int
    {
        return max(1, (int) config('auth_otp.expire_minutes', 10));
    }

    private function cacheKey(User $user): string
    {
        return 'staff-login-otp:'.$user->id;
    }

    /**
     * @return array{code?: string, sent_at?: int}|null
     */
    private function cachedPayload(User $user): ?array
    {
        $payload = Cache::get($this->cacheKey($user));

        return is_array($payload) ? $payload : null;
    }

    private function rememberPayload(User $user, string $code, int $expiresAtUnix): void
    {
        $ttl = max(1, $expiresAtUnix - now()->getTimestamp());
        Cache::put($this->cacheKey($user), [
            'code' => $code,
            'sent_at' => now()->getTimestamp(),
        ], $ttl);
    }

    private function forgetCache(User $user): void
    {
        Cache::forget($this->cacheKey($user));
    }

    private function clearOtp(User $user): void
    {
        $user->login_otp_hash = null;
        $user->login_otp_expires_at = null;
        $user->save();
        $this->forgetCache($user);
    }
}
