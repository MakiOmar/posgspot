<?php

namespace Tests\Feature\Storefront;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * `in_stock_only` on the digital games list is forwarded to Accounts (filters before pagination)
 * and re-checked on the normalized offers.
 */
class DigitalGamesInStockFilterTest extends TestCase
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

    private function fakeAccountsList(): void
    {
        Http::fake([
            'accounts.test/api/games/platform/*' => Http::response([
                'data' => [
                    [
                        'id' => 70,
                        'title' => 'Sellable',
                        'types' => [
                            'primary' => ['available' => false, 'stock' => 0, 'price' => 800],
                            'secondary' => ['available' => true, 'stock' => 2, 'price' => 600],
                        ],
                    ],
                    [
                        'id' => 71,
                        'title' => 'Offline only',
                        'types' => [
                            'primary' => ['available' => false, 'stock' => 0, 'price' => 800],
                            'secondary' => ['available' => false, 'stock' => 0, 'price' => 600],
                            'full' => ['available' => false, 'stock' => 0, 'price' => 900],
                        ],
                    ],
                ],
                'current_page' => 2,
                'last_page' => 3,
                'per_page' => 20,
                'total' => 45,
            ], 200),
        ]);
    }

    public function test_in_stock_only_is_forwarded_with_platform_and_page(): void
    {
        $this->fakeAccountsList();

        $this->getJson('/api/storefront/v1/digital/games?platform=4&page=2&in_stock_only=1')
            ->assertOk()
            ->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonPath('data.meta.last_page', 3)
            ->assertJsonPath('data.meta.total', 45)
            ->assertJsonCount(1, 'data.games')
            ->assertJsonPath('data.games.0.id', 70);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/games/platform/4')
            && ($request->data()['in_stock_only'] ?? null) == 1
            && ($request->data()['page'] ?? null) == 2);
    }

    public function test_without_filter_lists_every_game_and_does_not_forward_param(): void
    {
        $this->fakeAccountsList();

        $this->getJson('/api/storefront/v1/digital/games?platform=5')
            ->assertOk()
            ->assertJsonCount(2, 'data.games');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/games/platform/5')
            && ! array_key_exists('in_stock_only', $request->data()));
    }

    public function test_search_is_forwarded_and_accounts_results_are_not_refiltered_per_page(): void
    {
        $this->fakeAccountsList();

        // Accounts matched on code, so titles need not contain the term.
        $this->getJson('/api/storefront/v1/digital/games?platform=4&q=GAME-70&in_stock_only=1')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 45)
            ->assertJsonCount(1, 'data.games');

        Http::assertSent(fn (Request $request) => ($request->data()['q'] ?? null) === 'GAME-70'
            && ($request->data()['in_stock_only'] ?? null) == 1);
    }

    public function test_rejects_non_boolean_in_stock_only(): void
    {
        $this->getJson('/api/storefront/v1/digital/games?platform=5&in_stock_only=maybe')
            ->assertStatus(422);
    }
}
