<?php

namespace App\Services\Storefront\Shipping;

use App\Services\Storefront\StorefrontSettingService;
use App\StorefrontShippingMethod;
use App\StorefrontShippingZone;
use App\StorefrontShippingZoneLocation;
use Illuminate\Support\Facades\DB;

/**
 * One-time import of legacy flat_rate / free_shipping_threshold into default zones.
 */
class ShippingLegacyMigrator
{
    public function __construct(private StorefrontSettingService $settings)
    {
    }

    public function ensureDefaultZones(int $businessId): void
    {
        if (StorefrontShippingZone::where('business_id', $businessId)->exists()) {
            return;
        }

        $cfg = $this->settings->get($businessId);
        $flat = (float) ($cfg['shipping']['flat_rate'] ?? 0);
        $threshold = (float) ($cfg['shipping']['free_shipping_threshold'] ?? 0);

        DB::transaction(function () use ($businessId, $flat, $threshold) {
            $egypt = StorefrontShippingZone::create([
                'business_id' => $businessId,
                'name' => 'Egypt',
                'priority' => 10,
                'is_enabled' => true,
                'is_catch_all' => false,
            ]);

            StorefrontShippingZoneLocation::create([
                'zone_id' => $egypt->id,
                'type' => 'country',
                'code' => 'EG',
            ]);

            StorefrontShippingMethod::create([
                'zone_id' => $egypt->id,
                'type' => StorefrontShippingMethod::TYPE_FLAT_RATE,
                'title' => 'Standard delivery',
                'title_i18n' => ['en' => 'Standard delivery', 'ar' => 'توصيل عادي'],
                'settings' => [
                    'cost' => $flat,
                    'cost_per_item' => 0,
                    'eta_min_days' => 2,
                    'eta_max_days' => 5,
                ],
                'sort_order' => 10,
                'is_enabled' => true,
            ]);

            if ($threshold > 0) {
                StorefrontShippingMethod::create([
                    'zone_id' => $egypt->id,
                    'type' => StorefrontShippingMethod::TYPE_FREE_SHIPPING,
                    'title' => 'Free shipping',
                    'title_i18n' => ['en' => 'Free shipping', 'ar' => 'شحن مجاني'],
                    'settings' => [
                        'requires' => 'min_amount',
                        'min_amount' => $threshold,
                    ],
                    'sort_order' => 5,
                    'is_enabled' => true,
                ]);
            }

            StorefrontShippingMethod::create([
                'zone_id' => $egypt->id,
                'type' => StorefrontShippingMethod::TYPE_LOCAL_PICKUP,
                'title' => 'Pickup in store',
                'title_i18n' => ['en' => 'Pickup in store', 'ar' => 'استلام من الفرع'],
                'settings' => [
                    'cost' => 0,
                    'location_ids' => [],
                ],
                'sort_order' => 20,
                'is_enabled' => true,
            ]);

            $catchAll = StorefrontShippingZone::create([
                'business_id' => $businessId,
                'name' => 'Everywhere else',
                'priority' => 1000,
                'is_enabled' => true,
                'is_catch_all' => true,
            ]);

            StorefrontShippingMethod::create([
                'zone_id' => $catchAll->id,
                'type' => StorefrontShippingMethod::TYPE_FLAT_RATE,
                'title' => 'Standard delivery',
                'title_i18n' => ['en' => 'Standard delivery', 'ar' => 'توصيل عادي'],
                'settings' => [
                    'cost' => $flat,
                    'cost_per_item' => 0,
                ],
                'sort_order' => 10,
                'is_enabled' => true,
            ]);
        });

        app(ShippingZoneRepository::class)->flush($businessId);
    }
}
