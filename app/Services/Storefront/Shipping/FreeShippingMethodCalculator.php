<?php

namespace App\Services\Storefront\Shipping;

use App\StorefrontShippingMethod;

class FreeShippingMethodCalculator implements ShippingMethodCalculator
{
    public function supports(string $type): bool
    {
        return $type === StorefrontShippingMethod::TYPE_FREE_SHIPPING;
    }

    public function quote(StorefrontShippingMethod $method, array $context): ?array
    {
        $settings = $method->settings ?? [];
        $requires = $settings['requires'] ?? 'min_amount';
        $minAmount = (float) ($settings['min_amount'] ?? 0);
        $subtotal = (float) ($context['subtotal'] ?? 0);

        if ($requires === 'min_amount' && $minAmount > 0 && $subtotal < $minAmount) {
            return null;
        }

        return [
            'amount' => 0.0,
            'eta_label' => null,
            'meta' => [
                'type' => StorefrontShippingMethod::TYPE_FREE_SHIPPING,
                'min_amount' => $minAmount,
            ],
        ];
    }
}
