<?php

namespace App\Services\Storefront;

use App\Business;
use App\Contact;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use Illuminate\Validation\ValidationException;

/**
 * Reward points balance and redemption for storefront customers.
 */
class RewardPointsService
{
    public function __construct(
        private BusinessUtil $businessUtil,
        private TransactionUtil $transactionUtil
    ) {
    }

    public function isEnabled(int $businessId): bool
    {
        $business = $this->businessUtil->getDetails($businessId);

        return (int) ($business->enable_rp ?? 0) === 1;
    }

    public function getBusiness(int $businessId): Business
    {
        return $this->businessUtil->getDetails($businessId);
    }

    /**
     * Customer-facing balance summary (account page).
     */
    public function balancePayload(int $businessId, Contact $contact): array
    {
        $business = $this->getBusiness($businessId);
        $enabled = (int) ($business->enable_rp ?? 0) === 1;

        if (! $enabled) {
            return [
                'enabled' => false,
                'name' => $business->rp_name ?? 'Reward Points',
            ];
        }

        $available = (int) ($contact->total_rp ?? 0);
        $used = (int) ($contact->total_rp_used ?? 0);
        $expired = (int) ($contact->total_rp_expired ?? 0);
        $amountPerPoint = (float) ($business->redeem_amount_per_unit_rp ?? 0);
        $redeemDetails = $this->transactionUtil->getRewardRedeemDetails($businessId, $contact->id);

        return [
            'enabled' => true,
            'name' => $business->rp_name ?? 'Reward Points',
            'available' => $available,
            'used' => $used,
            'expired' => $expired,
            'value' => round($available * $amountPerPoint, 4),
            'max_redeem_points' => (int) $redeemDetails['points'],
            'amount_per_point' => $amountPerPoint,
            'min_redeem_points' => (int) ($business->min_redeem_point ?? 0),
            'max_redeem_points_limit' => (int) ($business->max_redeem_point ?? 0),
            'min_order_total_for_redeem' => (float) ($business->min_order_total_for_redeem ?? 0),
        ];
    }

    /**
     * Validate a redemption request for checkout preview (non-mutating).
     */
    public function validateRedemption(int $businessId, Contact $contact, int $requestedPoints, float $orderTotal): array
    {
        $business = $this->getBusiness($businessId);
        $base = $this->balancePayload($businessId, $contact);

        if (! $base['enabled']) {
            return [
                'is_valid' => false,
                'message' => 'Reward points are not enabled.',
                'requested_points' => $requestedPoints,
                'redeem_amount' => 0,
                ...$this->validationMeta($business, $contact, 0, $orderTotal),
            ];
        }

        if ($requestedPoints === 0) {
            return [
                'is_valid' => true,
                'message' => null,
                'requested_points' => 0,
                'redeem_amount' => 0,
                ...$this->validationMeta($business, $contact, (int) $base['max_redeem_points'], $orderTotal),
            ];
        }

        try {
            $resolved = $this->resolveCheckoutRedemption($businessId, $contact, $requestedPoints, $orderTotal);

            return [
                'is_valid' => true,
                'message' => null,
                'requested_points' => $resolved['points'],
                'redeem_amount' => $resolved['amount'],
                ...$this->validationMeta($business, $contact, (int) $base['max_redeem_points'], $orderTotal),
            ];
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Invalid reward points redemption.';

            return [
                'is_valid' => false,
                'message' => $message,
                'requested_points' => $requestedPoints,
                'redeem_amount' => 0,
                ...$this->validationMeta($business, $contact, (int) $base['max_redeem_points'], $orderTotal),
            ];
        }
    }

    /**
     * Resolve points and discount amount for checkout (throws if invalid).
     *
     * @return array{points: int, amount: float}
     */
    public function resolveCheckoutRedemption(int $businessId, Contact $contact, int $requestedPoints, float $orderTotal): array
    {
        if ($requestedPoints <= 0) {
            return ['points' => 0, 'amount' => 0.0];
        }

        if (! $this->isEnabled($businessId)) {
            throw ValidationException::withMessages(['reward_points' => ['Reward points are not enabled.']]);
        }

        if ($contact->is_default == 1) {
            throw ValidationException::withMessages(['reward_points' => ['Reward points cannot be redeemed for this account.']]);
        }

        $business = $this->getBusiness($businessId);
        $redeemDetails = $this->transactionUtil->getRewardRedeemDetails($businessId, $contact->id);
        $maxPoints = (int) $redeemDetails['points'];
        $amountPerPoint = (float) ($business->redeem_amount_per_unit_rp ?? 0);
        $minOrderTotal = (float) ($business->min_order_total_for_redeem ?? 0);

        if ($maxPoints <= 0) {
            throw ValidationException::withMessages(['reward_points' => ['You are not eligible to redeem reward points.']]);
        }

        if ($requestedPoints > $maxPoints) {
            throw ValidationException::withMessages(['reward_points' => ['Requested points exceed the maximum you can redeem.']]);
        }

        if ($orderTotal < $minOrderTotal) {
            throw ValidationException::withMessages(['reward_points' => ['Order total does not meet the minimum required to redeem points.']]);
        }

        $redeemAmount = round($requestedPoints * $amountPerPoint, 4);
        if ($redeemAmount > $orderTotal) {
            throw ValidationException::withMessages(['reward_points' => ['Redemption amount exceeds order total.']]);
        }

        return [
            'points' => $requestedPoints,
            'amount' => $redeemAmount,
        ];
    }

    private function validationMeta(Business $business, Contact $contact, int $maxPoints, float $orderTotal): array
    {
        return [
            'available_points' => (int) ($contact->total_rp ?? 0),
            'max_points' => $maxPoints,
            'amount_per_point' => (float) ($business->redeem_amount_per_unit_rp ?? 0),
            'min_redeem_points' => (int) ($business->min_redeem_point ?? 0),
            'max_redeem_points_limit' => (int) ($business->max_redeem_point ?? 0),
            'min_order_total_for_redeem' => (float) ($business->min_order_total_for_redeem ?? 0),
            'order_total' => round($orderTotal, 4),
        ];
    }
}
