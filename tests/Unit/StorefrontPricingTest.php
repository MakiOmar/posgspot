<?php

namespace Tests\Unit;

use App\Services\Storefront\StorefrontPricing;
use App\Variation;
use Tests\TestCase;

class StorefrontPricingTest extends TestCase
{
    public function test_uses_sale_price_when_lower_than_regular(): void
    {
        $variation = new Variation([
            'sell_price_inc_tax' => 100,
            'storefront_sale_price_inc_tax' => 80,
        ]);

        $pricing = app(StorefrontPricing::class)->resolve($variation);

        $this->assertTrue($pricing['on_sale']);
        $this->assertSame(80.0, $pricing['price']);
        $this->assertSame(100.0, $pricing['compare_at_price']);
        $this->assertSame(20, $pricing['sale_percent']);
    }

    public function test_ignores_invalid_or_higher_sale_price(): void
    {
        $variation = new Variation([
            'sell_price_inc_tax' => 100,
            'storefront_sale_price_inc_tax' => 120,
        ]);

        $pricing = app(StorefrontPricing::class)->resolve($variation);

        $this->assertFalse($pricing['on_sale']);
        $this->assertSame(100.0, $pricing['price']);
        $this->assertNull($pricing['compare_at_price']);
    }
}
