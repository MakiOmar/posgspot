<?php

namespace Tests\Feature\Storefront;

use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BostaDistrictsApiTest extends TestCase
{
    private int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'couriers' => [
                'bosta' => [
                    'enabled' => false,
                    'api_key' => null,
                    'staging' => false,
                ],
            ],
        ]);
    }

    public function test_districts_empty_when_bosta_disabled(): void
    {
        $this->getJson('/api/storefront/v1/geo/bosta-districts?state=C')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.districts', []);
    }

    public function test_districts_require_state(): void
    {
        $this->getJson('/api/storefront/v1/geo/bosta-districts')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_districts_from_zoning_when_configured(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'couriers' => [
                'bosta' => [
                    'enabled' => true,
                    'api_key' => 'test-bosta-key',
                    'staging' => false,
                ],
            ],
        ]);

        Http::fake([
            'app.bosta.co/api/v2/cities/getAllDistricts*' => Http::response([
                'data' => [
                    [
                        'cityCode' => 'C',
                        'cityName' => 'Cairo',
                        'cityOtherName' => 'القاهرة',
                        'dropOffAvailability' => true,
                        'districts' => [
                            [
                                'districtId' => 'dist-1',
                                'districtName' => 'Maadi',
                                'districtOtherName' => 'المعادي',
                                'zoneName' => 'Cairo',
                                'zoneOtherName' => 'القاهرة',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->getJson('/api/storefront/v1/geo/bosta-districts?state=C', [
            'X-Content-Locale' => 'en',
        ])
            ->assertOk()
            ->assertJsonPath('data.city_code', 'C')
            ->assertJsonPath('data.city_name', 'Cairo')
            ->assertJsonPath('data.districts.0.id', 'dist-1')
            ->assertJsonPath('data.districts.0.label', 'Cairo - Maadi');

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.couriers.bosta.enabled', true);
    }
}
