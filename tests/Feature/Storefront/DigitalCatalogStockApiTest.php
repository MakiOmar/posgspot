<?php

namespace Tests\Feature\Storefront;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Digital catalog stock must match Accounts per platform, not aggregate totals.
 */
class DigitalCatalogStockApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.accounts.base' => 'https://accounts.test',
            'services.accounts.phone' => '01000000000',
            'services.accounts.password' => 'secret',
        ]);
        Cache::flush();
    }

    public function test_list_marks_zero_stock_offer_unavailable(): void
    {
        Http::fake([
            'accounts.test/api/games/platform/*' => Http::response([
                'data' => [
                    [
                        'id' => 70,
                        'title' => 'GTA',
                        'primary_status' => true,
                        'types' => [
                            'primary' => ['available' => true, 'stock' => 0, 'price' => 800],
                            'secondary' => ['available' => true, 'stock' => 2, 'price' => 600],
                        ],
                    ],
                ],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 20,
                'total' => 1,
            ], 200),
        ]);

        $this->getJson('/api/storefront/v1/digital/games?platform=5')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.games.0.primary_status', false)
            ->assertJsonPath('data.games.0.total_primary_stock', 0)
            ->assertJsonPath('data.games.0.secondary_status', true)
            ->assertJsonPath('data.games.0.total_secondary_stock', 2);
    }

    public function test_game_detail_keeps_zero_stock_as_integer(): void
    {
        Http::fake([
            'accounts.test/api/games/70' => Http::response([
                'data' => [
                    'id' => 70,
                    'title' => 'GTA',
                    'ps5_primary_stock' => '0',
                    'ps4_primary_stock' => '12',
                    'ps5_primary_status' => 1,
                    'ps4_primary_status' => 1,
                ],
            ], 200),
        ]);

        $this->getJson('/api/storefront/v1/digital/games/70')
            ->assertOk()
            ->assertJsonPath('data.game.ps5_primary_stock', 0)
            ->assertJsonPath('data.game.ps4_primary_stock', 12);
    }

    public function test_check_stock_succeeds_when_available_without_quantity(): void
    {
        Http::fake([
            'accounts.test/api/login' => Http::response(['token' => 'test-token'], 200),
            'accounts.test/api/orders/check_stock' => Http::response([
                'is_available' => true,
            ], 200),
        ]);

        $this->postJson('/api/storefront/v1/digital/check-stock', [
            'game_id' => 66,
            'type' => 'primary',
            'platform' => '4',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_check_stock_returns_422_when_unavailable(): void
    {
        Http::fake([
            'accounts.test/api/login' => Http::response(['token' => 'test-token'], 200),
            'accounts.test/api/orders/check_stock' => Http::response([
                'is_available' => false,
                'stock' => 0,
            ], 200),
        ]);

        $this->postJson('/api/storefront/v1/digital/check-stock', [
            'game_id' => 70,
            'type' => 'primary',
            'platform' => '5',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
