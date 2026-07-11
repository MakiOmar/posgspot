<?php

namespace App\Services\Storefront\Shipping;

use App\BusinessLocation;
use App\StorefrontShippingMethod;

class LocalPickupMethodCalculator implements ShippingMethodCalculator
{
    public function supports(string $type): bool
    {
        return $type === StorefrontShippingMethod::TYPE_LOCAL_PICKUP;
    }

    public function quote(StorefrontShippingMethod $method, array $context): ?array
    {
        $settings = $method->settings ?? [];
        $cost = (float) ($settings['cost'] ?? 0);
        $allowedIds = $settings['location_ids'] ?? [];
        $pickupId = $context['pickup_location_id'] ?? null;

        // Availability does not require a delivery address.
        $zone = $method->relationLoaded('zone') ? $method->zone : $method->zone()->first();
        $businessId = $zone?->business_id;
        if (! $businessId) {
            return null;
        }

        $query = BusinessLocation::where('business_id', $businessId)
            ->where('enable_pickup', 1)
            ->Active();

        if (is_array($allowedIds) && count($allowedIds) > 0) {
            $query->whereIn('id', array_map('intval', $allowedIds));
        }

        $locations = $query->get(['id', 'name']);
        if ($locations->isEmpty()) {
            return null;
        }

        if ($pickupId && ! $locations->contains('id', (int) $pickupId)) {
            return null;
        }

        return [
            'amount' => max(0, round($cost, 4)),
            'eta_label' => null,
            'meta' => [
                'type' => StorefrontShippingMethod::TYPE_LOCAL_PICKUP,
                'location_ids' => $locations->pluck('id')->values()->all(),
                'locations' => $locations->map(fn ($l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                ])->values()->all(),
            ],
        ];
    }
}
