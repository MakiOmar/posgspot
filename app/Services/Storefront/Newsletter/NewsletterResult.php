<?php

namespace App\Services\Storefront\Newsletter;

/**
 * Outcome of a newsletter subscribe attempt.
 */
final class NewsletterResult
{
    public const STATUS_SUBSCRIBED = 'subscribed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ALREADY = 'already_subscribed';

    public const STATUS_FAILED = 'failed';

    public function __construct(
        public readonly string $status,
        public readonly string $message,
    ) {
    }

    public function ok(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUBSCRIBED,
            self::STATUS_PENDING,
            self::STATUS_ALREADY,
        ], true);
    }
}
