<?php

namespace App\Services\Storefront;

use App\Variation;

/**
 * Resolves effective storefront prices from variation regular + optional sale fields.
 */
class StorefrontPricing
{
    /**
     * @return array{price: float, compare_at_price: ?float, on_sale: bool, sale_percent: int}
     */
    public function resolve(Variation $variation): array
    {
        $regular = (float) $variation->sell_price_inc_tax;
        $sale = (float) ($variation->storefront_sale_price_inc_tax ?? 0);
        $onSale = $sale > 0 && $sale < $regular;
        $effective = $onSale ? $sale : $regular;
        $percent = $onSale && $regular > 0
            ? (int) round((1 - ($sale / $regular)) * 100)
            : 0;

        return [
            'price' => $effective,
            'compare_at_price' => $onSale ? $regular : null,
            'on_sale' => $onSale,
            'sale_percent' => $percent,
        ];
    }

    public function effectiveUnitPrice(Variation $variation): float
    {
        return $this->resolve($variation)['price'];
    }
}
