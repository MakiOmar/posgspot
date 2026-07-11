<?php

namespace App\Services\Storefront\Shipping;

use App\Product;
use App\StorefrontShippingMethod;
use App\StorefrontShippingZone;
use App\Variation;
use Illuminate\Support\Collection;

/**
 * Zone match + method quotes for storefront cart/checkout.
 */
class ShippingQuoteService
{
    /** @var ShippingMethodCalculator[] */
    private array $calculators;

    public function __construct(
        private ShippingZoneRepository $zones,
        private ShippingLegacyMigrator $migrator,
        FlatRateMethodCalculator $flat,
        FreeShippingMethodCalculator $free,
        LocalPickupMethodCalculator $pickup,
    ) {
        $this->calculators = [$flat, $free, $pickup];
    }

    /**
     * @param  array{country?:?string,state?:?string,city?:?string}  $destination
     * @param  array<int, array{variation_id:int,quantity:float}>  $items
     * @return array{
     *   available_rates: array<int, array>,
     *   shipping: float,
     *   shipping_rate: ?array,
     *   matched_zone_id: ?int
     * }
     */
    public function quote(
        int $businessId,
        float $subtotal,
        array $items,
        array $destination = [],
        ?string $shippingRateId = null,
        ?int $pickupLocationId = null,
        string $locale = 'en',
        bool $forceFreeShipping = false
    ): array {
        $this->migrator->ensureDefaultZones($businessId);

        $context = $this->buildContext($businessId, $subtotal, $items, $destination, $pickupLocationId, $locale);
        $zone = $this->matchZone($businessId, $destination, $pickupLocationId);

        $rates = [];
        if ($zone) {
            foreach ($zone->methods as $method) {
                if (! $method->is_enabled) {
                    continue;
                }
                $calc = $this->calculatorFor($method->type);
                if (! $calc) {
                    continue;
                }
                $quoted = $calc->quote($method, $context);
                if ($quoted === null) {
                    continue;
                }
                $amount = $forceFreeShipping && $method->type !== StorefrontShippingMethod::TYPE_LOCAL_PICKUP
                    ? 0.0
                    : (float) $quoted['amount'];

                $rates[] = [
                    'id' => ShippingRateId::encode((int) $method->id, $amount),
                    'method_id' => (int) $method->id,
                    'method_type' => $method->type,
                    'title' => $method->localizedTitle($locale),
                    'amount' => round($amount, 4),
                    'eta_label' => $quoted['eta_label'],
                    'meta' => $quoted['meta'] ?? [],
                ];
            }
        }

        // Pickup can appear even without a delivery address via catch-all / Egypt zone.
        if (empty($rates) && empty($destination['country']) && empty($destination['state'])) {
            $rates = $this->pickupOnlyRates($businessId, $context, $locale, $forceFreeShipping);
        }

        usort($rates, function ($a, $b) {
            if ($a['method_type'] === StorefrontShippingMethod::TYPE_LOCAL_PICKUP
                && $b['method_type'] !== StorefrontShippingMethod::TYPE_LOCAL_PICKUP) {
                return 1;
            }
            if ($b['method_type'] === StorefrontShippingMethod::TYPE_LOCAL_PICKUP
                && $a['method_type'] !== StorefrontShippingMethod::TYPE_LOCAL_PICKUP) {
                return -1;
            }

            return $a['amount'] <=> $b['amount'];
        });

        $selected = $this->selectRate($rates, $shippingRateId);

        return [
            'available_rates' => array_map(function (array $rate) {
                unset($rate['method_id']);

                return $rate;
            }, $rates),
            'shipping' => $selected ? (float) $selected['amount'] : 0.0,
            'shipping_rate' => $selected ? $this->publicRate($selected) : null,
            'matched_zone_id' => $zone?->id,
        ];
    }

    /**
     * Re-quote and verify a rate id; throws ValidationException-friendly message via null.
     *
     * @return array{ok:bool,rate:?array,shipping:float,available_rates:array,message:?string}
     */
    public function resolveSelectedRate(
        int $businessId,
        float $subtotal,
        array $items,
        array $destination,
        ?string $shippingRateId,
        ?int $pickupLocationId,
        string $locale = 'en',
        bool $forceFreeShipping = false
    ): array {
        $quoted = $this->quote(
            $businessId,
            $subtotal,
            $items,
            $destination,
            $shippingRateId,
            $pickupLocationId,
            $locale,
            $forceFreeShipping
        );

        if (empty($shippingRateId)) {
            return [
                'ok' => false,
                'rate' => null,
                'shipping' => 0.0,
                'available_rates' => $quoted['available_rates'],
                'message' => 'Please select a shipping method.',
            ];
        }

        $decoded = ShippingRateId::decode($shippingRateId);
        if (! $decoded) {
            return [
                'ok' => false,
                'rate' => null,
                'shipping' => 0.0,
                'available_rates' => $quoted['available_rates'],
                'message' => 'Invalid shipping rate.',
            ];
        }

        $match = null;
        foreach ($quoted['available_rates'] as $rate) {
            $d = ShippingRateId::decode($rate['id']);
            if ($d && $d['method_id'] === $decoded['method_id']
                && abs($d['amount'] - $decoded['amount']) < 0.0001) {
                $match = $rate;
                break;
            }
        }

        // Also accept if recomputed amount for same method matches newly quoted amount.
        if (! $match) {
            foreach ($quoted['available_rates'] as $rate) {
                $d = ShippingRateId::decode($rate['id']);
                if ($d && $d['method_id'] === $decoded['method_id']) {
                    // Stale amount — reject
                    return [
                        'ok' => false,
                        'rate' => null,
                        'shipping' => 0.0,
                        'available_rates' => $quoted['available_rates'],
                        'message' => 'Shipping rates changed. Please select a method again.',
                    ];
                }
            }

            return [
                'ok' => false,
                'rate' => null,
                'shipping' => 0.0,
                'available_rates' => $quoted['available_rates'],
                'message' => 'Selected shipping method is not available for this address.',
            ];
        }

        return [
            'ok' => true,
            'rate' => $match,
            'shipping' => (float) $match['amount'],
            'available_rates' => $quoted['available_rates'],
            'message' => null,
        ];
    }

    private function matchZone(int $businessId, array $destination, ?int $pickupLocationId): ?StorefrontShippingZone
    {
        $country = $this->normalizeCountryCode((string) ($destination['country'] ?? ''));
        $state = trim((string) ($destination['state'] ?? ''));

        $zones = $this->zones->enabledZones($businessId);
        $catchAll = null;

        foreach ($zones as $zone) {
            if ($zone->is_catch_all) {
                $catchAll = $zone;
                continue;
            }

            if ($this->zoneMatches($zone, $country, $state)) {
                return $zone;
            }
        }

        // Pickup-only browse: use first non-catch-all zone that has pickup, else catch-all.
        if ($country === '' && $state === '') {
            foreach ($zones as $zone) {
                if ($zone->is_catch_all) {
                    continue;
                }
                if ($zone->methods->contains('type', StorefrontShippingMethod::TYPE_LOCAL_PICKUP)) {
                    return $zone;
                }
            }
        }

        return $catchAll;
    }

    private function zoneMatches(StorefrontShippingZone $zone, string $country, string $state): bool
    {
        if ($country === '') {
            return false;
        }

        $locations = $zone->locations;
        if ($locations->isEmpty()) {
            return false;
        }

        $countryMatch = $locations->contains(function ($loc) use ($country) {
            return $loc->type === 'country' && strtoupper($loc->code) === $country;
        });

        $stateLocations = $locations->where('type', 'state');
        if ($stateLocations->isEmpty()) {
            return $countryMatch;
        }

        if ($state === '') {
            return false;
        }

        $stateNorm = $this->normalizeStateCode($country, $state);

        return $stateLocations->contains(function ($loc) use ($country, $stateNorm, $state) {
            $code = strtoupper(trim($loc->code));
            $needle = strtoupper($stateNorm);

            return $code === $needle
                || $code === strtoupper($country.':'.$stateNorm)
                || $code === strtoupper($state)
                || strcasecmp((string) $loc->code, $state) === 0;
        });
    }

    private function normalizeCountryCode(string $country): string
    {
        $country = strtoupper(trim($country));
        $aliases = [
            'EGYPT' => 'EG',
            'EGY' => 'EG',
        ];

        return $aliases[$country] ?? $country;
    }

    private function normalizeStateCode(string $country, string $state): string
    {
        $state = trim($state);
        if ($country === 'EG' && ! str_contains($state, '-')) {
            // Accept common geo codes like C / EG-C / Cairo.
            return $state;
        }

        return $state;
    }

    private function buildContext(
        int $businessId,
        float $subtotal,
        array $items,
        array $destination,
        ?int $pickupLocationId,
        string $locale
    ): array {
        $itemCount = 0.0;
        $weight = 0.0;
        $classIds = [];

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            $itemCount += $qty;
            $variationId = (int) ($item['variation_id'] ?? 0);
            if ($variationId <= 0) {
                continue;
            }
            $variation = Variation::with('product')->find($variationId);
            $product = $variation?->product;
            if ($product && (int) $product->business_id === $businessId) {
                if (! empty($product->shipping_class_id)) {
                    $classIds[] = (int) $product->shipping_class_id;
                }
                if (! empty($product->weight)) {
                    $weight += (float) $product->weight * $qty;
                }
            }
        }

        return [
            'subtotal' => $subtotal,
            'item_count' => $itemCount,
            'locale' => $locale,
            'destination' => $destination,
            'pickup_location_id' => $pickupLocationId,
            'shipping_class_ids' => array_values(array_unique($classIds)),
            'cart_weight' => $weight,
        ];
    }

    private function calculatorFor(string $type): ?ShippingMethodCalculator
    {
        foreach ($this->calculators as $calc) {
            if ($calc->supports($type)) {
                return $calc;
            }
        }

        return null;
    }

    private function pickupOnlyRates(
        int $businessId,
        array $context,
        string $locale,
        bool $forceFreeShipping
    ): array {
        $rates = [];
        foreach ($this->zones->enabledZones($businessId) as $zone) {
            foreach ($zone->methods as $method) {
                if ($method->type !== StorefrontShippingMethod::TYPE_LOCAL_PICKUP || ! $method->is_enabled) {
                    continue;
                }
                $calc = $this->calculatorFor($method->type);
                $quoted = $calc?->quote($method, $context);
                if ($quoted === null) {
                    continue;
                }
                $amount = (float) $quoted['amount'];
                $rates[] = [
                    'id' => ShippingRateId::encode((int) $method->id, $amount),
                    'method_id' => (int) $method->id,
                    'method_type' => $method->type,
                    'title' => $method->localizedTitle($locale),
                    'amount' => round($amount, 4),
                    'eta_label' => $quoted['eta_label'],
                    'meta' => $quoted['meta'] ?? [],
                ];
            }
        }

        return $rates;
    }

    private function selectRate(array $rates, ?string $shippingRateId): ?array
    {
        if (empty($rates)) {
            return null;
        }

        if ($shippingRateId) {
            $decoded = ShippingRateId::decode($shippingRateId);
            if ($decoded) {
                foreach ($rates as $rate) {
                    $d = ShippingRateId::decode($rate['id']);
                    if ($d && $d['method_id'] === $decoded['method_id']
                        && abs((float) $rate['amount'] - $decoded['amount']) < 0.0001) {
                        return $rate;
                    }
                }
            }
        }

        // Prefer cheapest non-pickup delivery rate for display default.
        foreach ($rates as $rate) {
            if ($rate['method_type'] !== StorefrontShippingMethod::TYPE_LOCAL_PICKUP) {
                return $rate;
            }
        }

        return $rates[0];
    }

    private function publicRate(array $rate): array
    {
        return [
            'id' => $rate['id'],
            'method_type' => $rate['method_type'],
            'title' => $rate['title'],
            'amount' => $rate['amount'],
            'eta_label' => $rate['eta_label'] ?? null,
            'meta' => $rate['meta'] ?? [],
        ];
    }
}
