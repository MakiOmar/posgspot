<?php

namespace Tests\Feature\Storefront;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Digital games list diagnostics expose internal hosts, so they only ship when APP_DEBUG is on.
 */
class DigitalGamesDebugPayloadTest extends TestCase
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

    private function fakeAccounts(int $status = 200): void
    {
        Http::fake([
            'accounts.test/api/games/platform/*' => Http::response($status === 200 ? [
                'data' => [[
                    'id' => 90,
                    'title' => 'Unsynced',
                    'types' => ['secondary' => ['available' => true, 'stock' => 1, 'price' => 500]],
                ]],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 20,
                'total' => 1,
            ] : ['message' => 'down'], $status),
        ]);
    }

    public function test_debug_block_is_hidden_when_app_debug_is_off(): void
    {
        config(['app.debug' => false]);
        $this->fakeAccounts();

        $this->getJson('/api/storefront/v1/digital/games?platform=5')
            ->assertOk()
            ->assertJsonCount(1, 'data.games')
            ->assertJsonMissingPath('data.debug');
    }

    public function test_debug_block_is_hidden_on_accounts_failure_when_app_debug_is_off(): void
    {
        config(['app.debug' => false]);
        $this->fakeAccounts(500);

        $this->getJson('/api/storefront/v1/digital/games?platform=5')
            ->assertOk()
            ->assertJsonCount(0, 'data.games')
            ->assertJsonMissingPath('data.debug');
    }

    public function test_debug_block_reports_unsynced_games_when_app_debug_is_on(): void
    {
        config(['app.debug' => true]);
        $this->fakeAccounts();

        $response = $this->getJson('/api/storefront/v1/digital/games?platform=5')
            ->assertOk()
            ->assertJsonPath('data.debug.accounts_ok', true);

        $this->assertStringStartsWith('1 of 1 listed games have no synced POS product', $response->json('data.debug.reason'));
    }
}
