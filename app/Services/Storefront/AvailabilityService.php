<?php

namespace App\Services\Storefront;

use App\BusinessLocation;
use App\Product;
use App\Utils\ProductUtil;
use App\Variation;

/**
 * Per-location stock availability for the PDP modal.
 */
class AvailabilityService
{
    public function __construct(
        private StorefrontSettingService $storefrontSettings,
        private SettingsApiService $settingsApi,
        private ProductUtil $productUtil
    ) {
    }

    public function getAvailability(int $businessId, int $productId, ?int $variationId = null): ?array
    {
        $product = Product::where('business_id', $businessId)
            ->active()
            ->where('not_for_selling', 0)
            ->find($productId);

        if (empty($product)) {
            return null;
        }

        $variation = $this->resolveVariation($product, $variationId);
        if (empty($variation)) {
            return null;
        }

        $settings = $this->storefrontSettings->get($businessId);

        // Availability reflects physical stock across ALL active business
        // locations (warehouses/branches), not just the public selling ones,
        // so customers can see every branch — including out-of-stock ones.
        $locations = BusinessLocation::where('business_id', $businessId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $locationRows = [];
        $inStockCount = 0;

        foreach ($locations as $loc) {
            $qty = $this->qtyAtLocation($product, $variation, $loc->id);
            $inStock = ! $product->enable_stock || $qty > 0;
            if ($inStock) {
                $inStockCount++;
            }

            $locationRows[] = [
                'location_id' => $loc->id,
                'name' => $loc->name,
                'address' => $this->settingsApi->composeAddress($loc),
                'phone' => $loc->mobile,
                'in_stock' => $inStock,
                'qty_available' => (float) $qty,
                'latitude' => $loc->latitude !== null ? (float) $loc->latitude : null,
                'longitude' => $loc->longitude !== null ? (float) $loc->longitude : null,
                'maps_url' => $this->settingsApi->mapsUrl($loc),
            ];
        }

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'variation_id' => $variation->id,
            'variation_name' => $variation->name,
            'in_stock_count' => $inStockCount,
            'cod_available' => (bool) ($settings['cod_enabled'] ?? false),
            'locations' => $locationRows,
        ];
    }

    private function resolveVariation(Product $product, ?int $variationId): ?Variation
    {
        if ($variationId) {
            return Variation::where('product_id', $product->id)
                ->whereNull('deleted_at')
                ->find($variationId);
        }

        return $product->variations()->whereNull('deleted_at')->first();
    }

    private function qtyAtLocation(Product $product, Variation $variation, int $locationId): float
    {
        if ($product->type === 'combo') {
            $combo = $variation->combo_variations ?? [];
            if (! empty($combo)) {
                return (float) $this->productUtil->calculateComboQuantity($locationId, $combo);
            }
        }

        if (! $product->enable_stock) {
            return 999;
        }

        $vld = $variation->variation_location_details()
            ->where('location_id', $locationId)
            ->first();

        return (float) ($vld->qty_available ?? 0);
    }
}
