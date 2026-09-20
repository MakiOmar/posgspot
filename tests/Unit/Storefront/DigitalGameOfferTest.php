<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\DigitalGameOffer;
use PHPUnit\Framework\TestCase;

class DigitalGameOfferTest extends TestCase
{
    public function test_stock_is_platform_strict_and_does_not_use_totals(): void
    {
        $game = [
            'ps4_primary_stock' => 45,
            'ps5_primary_stock' => 0,
            'total_primary_stock' => 45,
        ];

        $this->assertSame(45, DigitalGameOffer::stock($game, '4', 'primary'));
        $this->assertSame(0, DigitalGameOffer::stock($game, '5', 'primary'));
    }

    public function test_missing_platform_stock_is_zero_not_ps4_fallback(): void
    {
        $game = [
            'ps4_primary_stock' => 245,
            'total_primary_stock' => 245,
        ];

        $this->assertSame(0, DigitalGameOffer::stock($game, '5', 'primary'));
        $this->assertFalse(DigitalGameOffer::enabled($game, '5', 'primary'));
    }

    public function test_string_zero_stock_is_out(): void
    {
        $game = [
            'ps5_primary_stock' => '0',
            'ps5_primary_status' => 1,
            'ps5_primary_price' => 800,
        ];

        $this->assertSame(0, DigitalGameOffer::stock($game, '5', 'primary'));
        $this->assertTrue(DigitalGameOffer::enabled($game, '5', 'primary'));
    }

    public function test_list_offer_requires_available_and_stock(): void
    {
        $this->assertFalse(DigitalGameOffer::listOfferInStock(['available' => true, 'stock' => 0]));
        $this->assertFalse(DigitalGameOffer::listOfferInStock(['available' => false, 'stock' => 12]));
        $this->assertTrue(DigitalGameOffer::listOfferInStock(['available' => true, 'stock' => '3']));
    }

    public function test_check_stock_trusts_is_available_without_quantity(): void
    {
        $this->assertTrue(DigitalGameOffer::checkStockIndicatesAvailable(['is_available' => true]));
        $this->assertTrue(DigitalGameOffer::checkStockIndicatesAvailable([
            'data' => ['is_available' => true],
        ]));
        $this->assertFalse(DigitalGameOffer::checkStockIndicatesAvailable(['is_available' => false, 'stock' => 9]));
        $this->assertTrue(DigitalGameOffer::checkStockIndicatesAvailable(['stock' => 4]));
        $this->assertFalse(DigitalGameOffer::checkStockIndicatesAvailable([]));
    }

    public function test_normalize_detail_keeps_explicit_zero_integers(): void
    {
        $out = DigitalGameOffer::normalizeDetail([
            'id' => 70,
            'ps5_primary_stock' => '0',
            'ps4_primary_stock' => '12',
        ]);

        $this->assertSame(0, $out['ps5_primary_stock']);
        $this->assertSame(12, $out['ps4_primary_stock']);
        $this->assertSame(0, $out['ps5_secondary_stock']);
        $this->assertSame(0, $out['ps5_full_stock']);
    }

    public function test_full_offer_enabled_from_price_when_status_omitted(): void
    {
        $game = [
            'ps5_full_price' => 1100,
            'ps5_full_stock' => 2,
        ];

        $this->assertTrue(DigitalGameOffer::enabled($game, '5', 'full'));
        $this->assertSame(1100.0, DigitalGameOffer::price($game, '5', 'full'));
        $this->assertSame(2, DigitalGameOffer::stock($game, '5', 'full'));
        $this->assertSame('full', DigitalGameOffer::normalizeType('full'));
    }
}
