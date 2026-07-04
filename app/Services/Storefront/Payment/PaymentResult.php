<?php

namespace App\Services\Storefront\Payment;

/**
 * Outcome of applying a gateway payment notification to a storefront order.
 */
class PaymentResult
{
    public const STATUS_PAID = 'paid';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FAILED = 'failed';

    public const STATUS_INVALID = 'invalid';

    public function __construct(
        public readonly string $status,
        public readonly ?string $message = null,
        public readonly ?string $fawryRefNumber = null,
        public readonly ?string $referenceNumber = null,
        public readonly ?string $paymentMethod = null,
        public readonly ?string $expirationTime = null,
    ) {
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSuccess(): bool
    {
        return $this->isPaid() || $this->isPending();
    }
}
