<?php

namespace App\Services\Storefront;

use App\Contact;
use App\Coupon;
use App\CouponRedemption;
use App\Transaction;
use Illuminate\Validation\ValidationException;

/**
 * Storefront promo code validation, discount calculation, and redemption recording.
 */
class CouponService
{
    public function __construct(private StorefrontPricing $storefrontPricing)
    {
    }

    public function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    public function findByCode(int $businessId, string $code): ?Coupon
    {
        $normalized = $this->normalizeCode($code);
        if ($normalized === '') {
            return null;
        }

        return Coupon::where('business_id', $businessId)
            ->where('code', $normalized)
            ->first();
    }

    /**
     * Validate a coupon against cart lines and compute discount totals.
     *
     * @param  array<int, array{variation_id:int, product_id:int, category_id:?int, line_total:float, on_sale:bool}>  $lines
     * @return array{
     *   coupon: array{id:int, code:string, label:string, type:string, stack_with_reward_points:bool}|null,
     *   coupon_id: ?int,
     *   coupon_discount: float,
     *   free_shipping: bool,
     *   eligible_subtotal: float,
     *   subtotal: float,
     *   shipping: float,
     *   total: float,
     *   stack_with_reward_points: bool
     * }
     */
    public function applyToCart(
        int $businessId,
        ?string $code,
        array $lines,
        float $subtotal,
        array $settings,
        ?Contact $contact = null,
        string $channel = Coupon::CHANNEL_STOREFRONT
    ): array {
        $baseShipping = $this->calculateShipping($settings, $subtotal);
        $empty = [
            'coupon' => null,
            'coupon_id' => null,
            'coupon_discount' => 0.0,
            'free_shipping' => false,
            'eligible_subtotal' => round($subtotal, 4),
            'subtotal' => round($subtotal, 4),
            'shipping' => round($baseShipping, 4),
            'total' => round($subtotal + $baseShipping, 4),
            'stack_with_reward_points' => true,
        ];

        if ($code === null || trim($code) === '') {
            return $empty;
        }

        $coupon = $this->findByCode($businessId, $code);
        if (empty($coupon)) {
            throw ValidationException::withMessages(['coupon_code' => ['Invalid promo code.']]);
        }

        $this->assertCouponEligible($coupon, $lines, $subtotal, $contact, $channel);

        $eligibleSubtotal = $this->eligibleSubtotal($coupon, $lines);
        $discount = $this->computeDiscountAmount($coupon, $eligibleSubtotal);
        $freeShipping = $coupon->type === Coupon::TYPE_FREE_SHIPPING;
        $shipping = $freeShipping ? 0.0 : $baseShipping;
        $total = max(0, round($subtotal - $discount + $shipping, 4));

        return [
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'label' => $coupon->displayLabel(),
                'type' => $coupon->type,
                'stack_with_reward_points' => (bool) $coupon->stack_with_reward_points,
            ],
            'coupon_id' => $coupon->id,
            'coupon_discount' => round($discount, 4),
            'free_shipping' => $freeShipping,
            'eligible_subtotal' => round($eligibleSubtotal, 4),
            'subtotal' => round($subtotal, 4),
            'shipping' => round($shipping, 4),
            'total' => $total,
            'stack_with_reward_points' => (bool) $coupon->stack_with_reward_points,
        ];
    }

    /**
     * Lock coupon row and re-validate immediately before order commit.
     */
    public function lockForCheckout(int $couponId): Coupon
    {
        return Coupon::where('id', $couponId)->lockForUpdate()->firstOrFail();
    }

    public function recordRedemption(
        Coupon $coupon,
        Transaction $transaction,
        ?Contact $contact,
        float $discountAmount,
        string $channel = Coupon::CHANNEL_STOREFRONT
    ): CouponRedemption {
        $redemption = CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'business_id' => $transaction->business_id,
            'contact_id' => $contact?->id,
            'transaction_id' => $transaction->id,
            'discount_amount' => round($discountAmount, 4),
            'channel' => $channel,
            'redeemed_at' => now(),
        ]);

        $coupon->increment('times_used');

        return $redemption;
    }

    public function hasExistingRedemption(int $couponId, int $transactionId): bool
    {
        return CouponRedemption::where('coupon_id', $couponId)
            ->where('transaction_id', $transactionId)
            ->exists();
    }

    /**
     * @param  array<int, array{variation_id:int, product_id:int, category_id:?int, line_total:float, on_sale:bool}>  $lines
     */
    private function assertCouponEligible(
        Coupon $coupon,
        array $lines,
        float $subtotal,
        ?Contact $contact,
        string $channel
    ): void {
        if (! $coupon->is_active) {
            throw ValidationException::withMessages(['coupon_code' => ['This promo code is not active.']]);
        }

        if (! $coupon->supportsChannel($channel)) {
            throw ValidationException::withMessages(['coupon_code' => ['This promo code cannot be used online.']]);
        }

        $now = now();
        if (! empty($coupon->starts_at) && $coupon->starts_at->gt($now)) {
            throw ValidationException::withMessages(['coupon_code' => ['This promo code is not valid yet.']]);
        }
        if (! empty($coupon->ends_at) && $coupon->ends_at->lt($now)) {
            throw ValidationException::withMessages(['coupon_code' => ['This promo code has expired.']]);
        }

        if (! empty($coupon->max_uses_total) && (int) $coupon->times_used >= (int) $coupon->max_uses_total) {
            throw ValidationException::withMessages(['coupon_code' => ['This promo code has reached its usage limit.']]);
        }

        $eligibleSubtotal = $this->eligibleSubtotal($coupon, $lines);
        if ($eligibleSubtotal <= 0) {
            throw ValidationException::withMessages(['coupon_code' => ['No eligible items in your cart for this promo code.']]);
        }

        $minSubtotal = (float) ($coupon->min_order_subtotal ?? 0);
        if ($minSubtotal > 0 && $eligibleSubtotal < $minSubtotal) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Minimum order amount not met for this promo code.'],
            ]);
        }

        if ($coupon->first_order_only) {
            if (empty($contact)) {
                throw ValidationException::withMessages(['coupon_code' => ['Sign in to use this promo code.']]);
            }
            if ($this->contactHasPriorStorefrontOrder($contact->id)) {
                throw ValidationException::withMessages(['coupon_code' => ['This promo code is for first orders only.']]);
            }
        }

        if (! empty($coupon->max_uses_per_customer)) {
            if (empty($contact)) {
                throw ValidationException::withMessages(['coupon_code' => ['Sign in to use this promo code.']]);
            }
            $used = CouponRedemption::where('coupon_id', $coupon->id)
                ->where('contact_id', $contact->id)
                ->count();
            if ($used >= (int) $coupon->max_uses_per_customer) {
                throw ValidationException::withMessages(['coupon_code' => ['You have already used this promo code.']]);
            }
        }
    }

    /**
     * @param  array<int, array{variation_id:int, product_id:int, category_id:?int, line_total:float, on_sale:bool}>  $lines
     */
    private function eligibleSubtotal(Coupon $coupon, array $lines): float
    {
        $coupon->loadMissing(['categories', 'variations']);

        $categoryIds = $coupon->categories->pluck('id')->map(fn ($id) => (int) $id)->all();
        $variationIds = $coupon->variations->pluck('id')->map(fn ($id) => (int) $id)->all();

        $total = 0.0;
        foreach ($lines as $line) {
            if ($coupon->exclude_sale_items && ! empty($line['on_sale'])) {
                continue;
            }

            if ($coupon->applies_to === Coupon::APPLIES_PRODUCTS) {
                if (! in_array((int) $line['variation_id'], $variationIds, true)) {
                    continue;
                }
            } elseif ($coupon->applies_to === Coupon::APPLIES_CATEGORIES) {
                $categoryId = (int) ($line['category_id'] ?? 0);
                if ($categoryId === 0 || ! in_array($categoryId, $categoryIds, true)) {
                    continue;
                }
            }

            $total += (float) $line['line_total'];
        }

        return $total;
    }

    private function computeDiscountAmount(Coupon $coupon, float $eligibleSubtotal): float
    {
        if ($coupon->type === Coupon::TYPE_FREE_SHIPPING) {
            return 0.0;
        }

        if ($coupon->type === Coupon::TYPE_PERCENT_ORDER) {
            $percent = max(0, (float) $coupon->discount_amount);
            $discount = $eligibleSubtotal * ($percent / 100);
            $cap = $coupon->max_discount_amount;
            if ($cap !== null && $cap > 0) {
                $discount = min($discount, (float) $cap);
            }

            return min($discount, $eligibleSubtotal);
        }

        if ($coupon->type === Coupon::TYPE_FIXED_ORDER) {
            return min(max(0, (float) $coupon->discount_amount), $eligibleSubtotal);
        }

        return 0.0;
    }

    private function calculateShipping(array $settings, float $subtotal): float
    {
        $flat = (float) ($settings['shipping']['flat_rate'] ?? 0);
        $threshold = (float) ($settings['shipping']['free_shipping_threshold'] ?? 0);

        if ($threshold > 0 && $subtotal >= $threshold) {
            return 0.0;
        }

        return $flat;
    }

    private function contactHasPriorStorefrontOrder(int $contactId): bool
    {
        return Transaction::where('contact_id', $contactId)
            ->where('source', 'storefront')
            ->where('status', 'final')
            ->where('type', 'sell')
            ->exists();
    }

    /**
     * Build coupon-aware line metadata from cart validation items.
     *
     * @param  array<int, array<string, mixed>>  $items  raw cart items
     * @return array{lines: array<int, array>, subtotal: float}
     */
    public function buildCouponLines(int $businessId, array $items): array
    {
        $lines = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $variation = \App\Variation::with('product')->find((int) ($item['variation_id'] ?? 0));
            if (empty($variation) || empty($variation->product) || (int) $variation->product->business_id !== $businessId) {
                continue;
            }

            $qty = (float) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $pricing = $this->storefrontPricing->resolve($variation);
            $lineTotal = $pricing['price'] * $qty;
            $subtotal += $lineTotal;

            $lines[] = [
                'variation_id' => (int) $variation->id,
                'product_id' => (int) $variation->product_id,
                'category_id' => $variation->product->category_id ? (int) $variation->product->category_id : null,
                'line_total' => $lineTotal,
                'on_sale' => (bool) $pricing['on_sale'],
            ];
        }

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
        ];
    }
}
