<?php

namespace App\Services\Storefront;

use App\Product;
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
        private ProductUtil $productUtil
    ) {
    }

    public function validate(int $businessId, array $items, ?int $locationId = null): array
    {
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

            $unitPrice = (float) $variation->sell_price_inc_tax;
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
            ];
            $lines[] = $line;

            $productLine = [
                'product_id' => $product->id,
                'variation_id' => $variation->id,
                'quantity' => $qty,
                'unit_price' => $variation->default_sell_price,
                'unit_price_inc_tax' => $unitPrice,
                'item_tax' => max(0, $unitPrice - (float) $variation->default_sell_price) * $qty,
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

        return [
            'lines' => $lines,
            'products_payload' => $productsPayload,
            'subtotal' => round($subtotal, 4),
            'shipping' => round($shipping, 4),
            'total' => round($total, 4),
        ];
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
