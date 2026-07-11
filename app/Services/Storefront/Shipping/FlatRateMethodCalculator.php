<?php

namespace App\Services\Storefront\Shipping;

use App\StorefrontShippingMethod;

class FlatRateMethodCalculator implements ShippingMethodCalculator
{
    public function supports(string $type): bool
    {
        return $type === StorefrontShippingMethod::TYPE_FLAT_RATE;
    }

    public function quote(StorefrontShippingMethod $method, array $context): ?array
    {
        $settings = $method->settings ?? [];
        $cost = (float) ($settings['cost'] ?? 0);
        $perItem = (float) ($settings['cost_per_item'] ?? 0);
        $amount = $cost + ($perItem * (float) ($context['item_count'] ?? 0));

        // Optional per shipping-class add-ons (Phase 2).
        $classCosts = $settings['class_costs'] ?? [];
        if (is_array($classCosts) && ! empty($context['shipping_class_ids'])) {
            foreach ($context['shipping_class_ids'] as $classId) {
                $amount += (float) ($classCosts[(string) $classId] ?? $classCosts[$classId] ?? 0);
            }
        }

        // Optional weight surcharge: cost_per_kg * weight.
        $perKg = (float) ($settings['cost_per_kg'] ?? 0);
        if ($perKg > 0 && ! empty($context['cart_weight'])) {
            $amount += $perKg * (float) $context['cart_weight'];
        }

        return [
            'amount' => max(0, round($amount, 4)),
            'eta_label' => $this->etaLabel($settings, $context['locale'] ?? 'en'),
            'meta' => ['type' => StorefrontShippingMethod::TYPE_FLAT_RATE],
        ];
    }

    private function etaLabel(array $settings, string $locale): ?string
    {
        $min = isset($settings['eta_min_days']) ? (int) $settings['eta_min_days'] : null;
        $max = isset($settings['eta_max_days']) ? (int) $settings['eta_max_days'] : null;
        if ($min === null && $max === null) {
            return null;
        }
        if ($min !== null && $max !== null && $min !== $max) {
            return $locale === 'ar'
                ? "{$min}–{$max} أيام عمل"
                : "{$min}–{$max} business days";
        }
        $days = $max ?? $min;

        return $locale === 'ar'
            ? "{$days} أيام عمل"
            : "{$days} business days";
    }
}
