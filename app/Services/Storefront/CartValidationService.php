<?php

namespace App\Services\Storefront;

use App\Product;
use App\Contact;
use App\Utils\ProductUtil;
use App\Variation;
use Illuminate\Validation\ValidationException;

/**
 * Stateless cart validation — re-prices and re-checks stock before checkout.
 */
class CartValidationService
{
    public function __construct(
        private StorefrontSettingService $storefrontSettings,
        private ProductUtil $productUtil,
        private StorefrontPricing $storefrontPricing,
        private CouponService $couponService
    ) {
    }

    public function validate(
        int $businessId,
        array $items,
        ?int $locationId = null,
        ?string $couponCode = null,
        ?Contact $contact = null,
        ?array $couponCodes = null
    ): array {
        $locationIds = $this->storefrontSettings->getSellingLocationIds($businessId);
        if (empty($locationIds)) {
            throw ValidationException::withMessages(['cart' => ['Storefront is not configured with selling locations.']]);
        }

        if ($locationId && ! in_array($locationId, $locationIds, true)) {
            throw ValidationException::withMessages(['location_id' => ['Invalid fulfillment location.']]);
        }

        $settings = $this->storefrontSettings->get($businessId);
        $lines = [];
        $productsPayload = [];
        $subtotal = 0;

        foreach ($items as $index => $item) {
            $variationId = (int) ($item['variation_id'] ?? 0);
            $qty = (float) ($item['quantity'] ?? 0);

            if ($qty <= 0) {
                throw ValidationException::withMessages(["items.$index.quantity" => ['Invalid quantity.']]);
            }

            $variation = Variation::with('product')->find($variationId);
            if (empty($variation) || empty($variation->product) || $variation->product->business_id != $businessId) {
                throw ValidationException::withMessages(["items.$index.variation_id" => ['Product not found.']]);
            }

            $product = $variation->product;
            if ($product->is_inactive || $product->not_for_selling) {
                throw ValidationException::withMessages(["items.$index.variation_id" => ['Product is not available.']]);
            }

            $pricing = $this->storefrontPricing->resolve($variation);
            $unitPrice = $pricing['price'];
            $lineTotal = $unitPrice * $qty;
            $subtotal += $lineTotal;

            $stockLocationIds = $locationId ? [$locationId] : $locationIds;
            $inStock = $this->checkStock($product, $variation, $qty, $stockLocationIds);
            if (! $inStock) {
                $message = $locationId
                    ? 'Insufficient stock at the selected store.'
                    : 'Insufficient stock.';
                throw ValidationException::withMessages(["items.$index.quantity" => [$message]]);
            }

            $line = [
                'variation_id' => $variation->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'variation_name' => $variation->name,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'in_stock' => true,
                'category_id' => $product->category_id ? (int) $product->category_id : null,
                'on_sale' => (bool) $pricing['on_sale'],
            ];
            $lines[] = $line;

            $exclusive = (float) $variation->default_sell_price;
            if ($exclusive <= 0) {
                $exclusive = $unitPrice;
            }
            $productLine = [
                'product_id' => $product->id,
                'variation_id' => $variation->id,
                'quantity' => $qty,
                'unit_price' => $exclusive,
                'unit_price_inc_tax' => $unitPrice,
                // Per-unit tax; createOrUpdateSellLines stores this as unit tax.
                'item_tax' => max(0, $unitPrice - $exclusive),
                'tax_id' => $product->tax,
                'enable_stock' => $product->enable_stock,
            ];

            if ($product->type === 'combo') {
                $productLine['product_type'] = 'combo';
                $productLine['combo'] = $this->productUtil->buildComboSellLinePayload($variation, $qty);
            }

            $productsPayload[] = $productLine;
        }

        $shipping = $this->calculateShipping($settings, $subtotal);
        $total = $subtotal + $shipping;

        $result = [
            'lines' => $lines,
            'products_payload' => $productsPayload,
            'subtotal' => round($subtotal, 4),
            'shipping' => round($shipping, 4),
            'total' => round($total, 4),
        ];

        return $this->mergeCouponTotals(
            $businessId,
            $result,
            $settings,
            $this->couponService->normalizeCodes($couponCode, $couponCodes),
            $contact
        );
    }

    private function checkStock(Product $product, Variation $variation, float $qty, array $locationIds): bool
    {
        if (! $product->enable_stock) {
            return true;
        }

        $available = $variation->variation_location_details()
            ->whereIn('location_id', $locationIds)
            ->sum('qty_available');

        return (float) $available >= $qty;
    }

    /**
     * Inspect cart lines without failing on stock — returns per-line max quantity for client-side resolution.
     */
    public function inspect(
        int $businessId,
        array $items,
        ?int $locationId = null,
        ?string $couponCode = null,
        ?Contact $contact = null,
        ?array $couponCodes = null
    ): array {
        $locationIds = $this->storefrontSettings->getSellingLocationIds($businessId);
        if (empty($locationIds)) {
            throw ValidationException::withMessages(['cart' => ['Storefront is not configured with selling locations.']]);
        }

        if ($locationId && ! in_array($locationId, $locationIds, true)) {
            throw ValidationException::withMessages(['location_id' => ['Invalid fulfillment location.']]);
        }

        $settings = $this->storefrontSettings->get($businessId);
        $stockLocationIds = $locationId ? [$locationId] : $locationIds;
        $lineStatus = [];
        $lines = [];
        $subtotal = 0;

        foreach ($items as $index => $item) {
            $variationId = (int) ($item['variation_id'] ?? 0);
            $requested = (float) ($item['quantity'] ?? 0);

            if ($requested <= 0) {
                throw ValidationException::withMessages(["items.$index.quantity" => ['Invalid quantity.']]);
            }

            $status = [
                'variation_id' => $variationId,
                'requested_quantity' => $requested,
                'max_quantity' => 0.0,
                'unit_price' => 0.0,
                'name' => '',
                'variation_name' => '',
                'available' => false,
                'stock_tracked' => true,
            ];

            $variation = Variation::with('product')->find($variationId);
            if (empty($variation) || empty($variation->product) || $variation->product->business_id != $businessId) {
                $lineStatus[] = $status;

                continue;
            }

            $product = $variation->product;
            $pricing = $this->storefrontPricing->resolve($variation);
            $unitPrice = $pricing['price'];
            $status['name'] = $product->name;
            $status['variation_name'] = $variation->name;
            $status['unit_price'] = $unitPrice;

            if ($product->is_inactive || $product->not_for_selling) {
                $lineStatus[] = $status;

                continue;
            }

            if (! $product->enable_stock) {
                $status['stock_tracked'] = false;
                $status['max_quantity'] = null;
                $status['available'] = true;
            } else {
                $available = (float) $variation->variation_location_details()
                    ->whereIn('location_id', $stockLocationIds)
                    ->sum('qty_available');
                $status['max_quantity'] = max(0, $available);
                $status['available'] = $available >= $requested;
            }

            $lineStatus[] = $status;

            if ($status['available']) {
                $lineTotal = $unitPrice * $requested;
                $subtotal += $lineTotal;
                $lines[] = [
                    'variation_id' => $variation->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'variation_name' => $variation->name,
                    'quantity' => $requested,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'in_stock' => true,
                    'category_id' => $product->category_id ? (int) $product->category_id : null,
                    'on_sale' => (bool) $pricing['on_sale'],
                ];
            }
        }

        $shipping = $this->calculateShipping($settings, $subtotal);
        $total = $subtotal + $shipping;

        $result = [
            'line_status' => $lineStatus,
            'lines' => $lines,
            'subtotal' => round($subtotal, 4),
            'shipping' => round($shipping, 4),
            'total' => round($total, 4),
        ];

        return $this->mergeCouponTotals(
            $businessId,
            $result,
            $settings,
            $this->couponService->normalizeCodes($couponCode, $couponCodes),
            $contact
        );
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<int, string>  $couponCodes
     * @return array<string, mixed>
     */
    private function mergeCouponTotals(
        int $businessId,
        array $result,
        array $settings,
        array $couponCodes,
        ?Contact $contact
    ): array {
        if ($couponCodes === []) {
            $result['coupon'] = null;
            $result['coupons'] = [];
            $result['applied_coupons'] = [];
            $result['coupon_discount'] = 0;
            $result['eligible_subtotal'] = $result['subtotal'];
            $result['coupon_ids'] = [];
            $result['coupon_codes'] = [];

            return $result;
        }

        $couponLines = array_map(fn ($line) => [
            'variation_id' => (int) $line['variation_id'],
            'product_id' => (int) ($line['product_id'] ?? 0),
            'category_id' => $line['category_id'] ?? null,
            'line_total' => (float) $line['line_total'],
            'on_sale' => (bool) ($line['on_sale'] ?? false),
        ], $result['lines']);

        $applied = $this->couponService->applyMultipleToCart(
            $businessId,
            $couponCodes,
            $couponLines,
            (float) $result['subtotal'],
            $settings,
            $contact
        );

        $result['coupon'] = $applied['coupon'];
        $result['coupons'] = $applied['coupons'] ?? [];
        $result['applied_coupons'] = $applied['applied_coupons'] ?? [];
        $result['coupon_discount'] = $applied['coupon_discount'];
        $result['eligible_subtotal'] = $applied['eligible_subtotal'];
        $result['shipping'] = $applied['shipping'];
        $result['total'] = $applied['total'];
        $result['coupon_id'] = $applied['coupon_id'];
        $result['coupon_ids'] = $applied['coupon_ids'] ?? [];
        $result['coupon_codes'] = $applied['coupon_codes'] ?? [];
        $result['stack_with_reward_points'] = $applied['stack_with_reward_points'];

        return $result;
    }

    private function calculateShipping(array $settings, float $subtotal): float
    {
        $flat = (float) ($settings['shipping']['flat_rate'] ?? 0);
        $threshold = (float) ($settings['shipping']['free_shipping_threshold'] ?? 0);

        if ($threshold > 0 && $subtotal >= $threshold) {
            return 0;
        }

        return $flat;
    }
}
