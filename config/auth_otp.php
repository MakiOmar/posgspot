<?php

/**
 * Staff web login (POS /login): email OTP and/or username + password.
 *
 * STAFF_LOGIN_METHOD:
 * - otp      — email code only (production-safe default after theft hardening)
 * - password — username + password only
 * - both     — password by default; switch to OTP via ?method=otp (and back)
 */
$method = strtolower(trim((string) env('STAFF_LOGIN_METHOD', 'otp')));
if (! in_array($method, ['otp', 'password', 'both'], true)) {
    $method = 'otp';
}

return [
    /**
     * Which staff web login paths are available.
     *
     * @var string otp|password|both
     */
    'login_method' => $method,

    /** Minutes until a issued login code expires. */
    'expire_minutes' => max(1, (int) env('AUTH_OTP_EXPIRE_MINUTES', 10)),

    /** Max OTP issue requests per minute (IP + email). */
    'issue_per_minute' => max(1, (int) env('AUTH_OTP_ISSUE_PER_MINUTE', 5)),

    /**
     * Minimum seconds between OTP emails for the same user.
     * Within this window a new request is rejected (no mail); after it, a still-valid
     * code is re-sent unchanged instead of minting a new one.
     */
    'resend_cooldown_seconds' => max(1, (int) env('AUTH_OTP_RESEND_COOLDOWN_SECONDS', 60)),

    /** Max failed username/password attempts before lockout (per username+IP). */
    'password_max_attempts' => max(1, (int) env('AUTH_PASSWORD_MAX_ATTEMPTS', 5)),

    /** Lockout window in minutes after too many password failures. */
    'password_decay_minutes' => max(1, (int) env('AUTH_PASSWORD_DECAY_MINUTES', 1)),
];
