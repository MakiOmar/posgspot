<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Services\Storefront\Shipping\ShippingLegacyMigrator;
use App\Services\Storefront\Shipping\ShippingQuoteService;
use App\Services\Storefront\Shipping\ShippingRateId;
use App\Services\Storefront\StorefrontSettingService;
use Tests\TestCase;

class ShippingZonesApiTest extends TestCase
{
    protected int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $location = BusinessLocation::where('business_id', $this->businessId)->first();
        if (empty($location)) {
            return;
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
            'shipping' => [
                'flat_rate' => 40,
                'free_shipping_threshold' => 1000,
            ],
        ]);
        \Illuminate\Support\Facades\Cache::flush();
        app(ShippingLegacyMigrator::class)->ensureDefaultZones($this->businessId);
    }

    public function test_cart_validate_returns_available_rates_for_egypt(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->first();
        if (empty($location)) {
            $this->markTestSkipped('No location');
        }

        $variation = \App\Variation::whereHas('product', fn ($q) => $q->where('business_id', $this->businessId))
            ->first();
        if (empty($variation)) {
            $this->markTestSkipped('No variation');
        }

        $response = $this->postJson('/api/storefront/v1/cart/validate', [
            'items' => [['variation_id' => $variation->id, 'quantity' => 1]],
            'destination' => ['country' => 'EG', 'state' => 'C'],
            'location_id' => $location->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['shipping', 'available_rates', 'subtotal']]);

        $this->assertNotEmpty($response->json('data.available_rates'));
    }

    public function test_shipping_rate_id_round_trip(): void
    {
        $id = ShippingRateId::encode(12, 40.5);
        $decoded = ShippingRateId::decode($id);

        $this->assertNotNull($decoded);
        $this->assertSame(12, $decoded['method_id']);
        $this->assertEqualsWithDelta(40.5, $decoded['amount'], 0.0001);
    }

    public function test_quote_service_migrates_legacy_zones(): void
    {
        $quoted = app(ShippingQuoteService::class)->quote(
            $this->businessId,
            100,
            [],
            ['country' => 'EG'],
            null,
            null,
            'en',
            false
        );

        $this->assertArrayHasKey('available_rates', $quoted);
        $this->assertNotEmpty($quoted['available_rates']);
    }
}
