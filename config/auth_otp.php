<?php

/**
 * Staff web login email OTP (POS /login).
 */
return [
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
];
