<?php

namespace App\Services\Storefront\Shipping;

use App\StorefrontShippingMethod;
use App\StorefrontShippingZone;
use App\StorefrontShippingZoneLocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cached shipping zone tree per business.
 */
class ShippingZoneRepository
{
    private const CACHE_TTL = 900; // 15 minutes

    public function cacheKey(int $businessId): string
    {
        return 'storefront_shipping_zones_'.$businessId;
    }

    public function flush(int $businessId): void
    {
        Cache::forget($this->cacheKey($businessId));
    }

    /**
     * @return Collection<int, StorefrontShippingZone>
     */
    public function enabledZones(int $businessId): Collection
    {
        return Cache::remember($this->cacheKey($businessId), self::CACHE_TTL, function () use ($businessId) {
            return StorefrontShippingZone::query()
                ->where('business_id', $businessId)
                ->where('is_enabled', true)
                ->with([
                    'locations',
                    'methods' => fn ($q) => $q->where('is_enabled', true)->orderBy('sort_order')->orderBy('id'),
                ])
                ->orderBy('priority')
                ->orderBy('id')
                ->get();
        });
    }

    public function findMethod(int $businessId, int $methodId): ?StorefrontShippingMethod
    {
        $method = StorefrontShippingMethod::with('zone')->find($methodId);
        if (empty($method?->zone) || (int) $method->zone->business_id !== $businessId) {
            return null;
        }

        return $method;
    }
}
