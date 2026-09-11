<?php

namespace Tests\Feature\Storefront;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeviceTrackApiTest extends TestCase
{
    public function test_track_requires_phone(): void
    {
        $this->postJson('/api/storefront/v1/device/track', [])
            ->assertStatus(422);
    }

    public function test_track_proxies_success_from_accounts(): void
    {
        config(['services.accounts.base' => 'https://accounts.test']);

        Http::fake([
            'accounts.test/api/device/track' => Http::response([
                'success' => true,
                'message' => 'Services found.',
                'count' => 1,
                'data' => [
                    [
                        'id' => 10,
                        'tracking_code' => 'DRTEST001',
                        'status' => 'processing',
                        'status_display' => 'Processing',
                        'device_serial_number' => 'SN1',
                        'notes' => null,
                        'submitted_at' => '2026-09-05T12:00:00+03:00',
                        'status_updated_at' => '2026-09-05T12:00:00+03:00',
                        'client_name' => 'Test User',
                        'phone' => '+201000000000',
                        'device_model' => [
                            'id' => 1,
                            'name' => 'PS5',
                            'brand' => 'Sony',
                            'full_name' => 'Sony PS5',
                        ],
                        'store_profile' => [
                            'id' => 1,
                            'name' => 'CITY STARS',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->postJson('/api/storefront/v1/device/track', [
            'phone_number' => '+201000000000',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.services.0.tracking_code', 'DRTEST001')
            ->assertJsonPath('data.services.0.device_model.full_name', 'Sony PS5');
    }

    public function test_track_maps_upstream_not_found(): void
    {
        config(['services.accounts.base' => 'https://accounts.test']);

        Http::fake([
            'accounts.test/api/device/track' => Http::response([
                'success' => false,
                'message' => 'No services found for this phone number.',
                'count' => 0,
                'data' => [],
            ], 404),
        ]);

        $this->postJson('/api/storefront/v1/device/track', [
            'phone' => '+201000000001',
        ])
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_account_device_services_requires_auth(): void
    {
        $this->getJson('/api/storefront/v1/account/device-services')
            ->assertStatus(401);
    }
}
