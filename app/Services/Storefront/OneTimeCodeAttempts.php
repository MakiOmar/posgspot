<?php

namespace App\Services\Storefront;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Counts wrong guesses against a 6-digit emailed code so it is burned after a few failures.
 * IP throttles alone do not stop a distributed brute force of a 1-in-a-million code.
 */
class OneTimeCodeAttempts
{
    public const MAX_FAILURES = 5;

    private const WINDOW_SECONDS = 3600;

    /** Record a failed guess; returns true when the code must now be invalidated. */
    public function recordFailure(string $purpose, string $subject): bool
    {
        $key = $this->key($purpose, $subject);
        RateLimiter::hit($key, self::WINDOW_SECONDS);

        if (RateLimiter::attempts($key) < self::MAX_FAILURES) {
            return false;
        }
        RateLimiter::clear($key);

        return true;
    }

    public function clear(string $purpose, string $subject): void
    {
        RateLimiter::clear($this->key($purpose, $subject));
    }

    private function key(string $purpose, string $subject): string
    {
        return 'storefront-code-fail:'.$purpose.':'.sha1(strtolower(trim($subject)));
    }
}
