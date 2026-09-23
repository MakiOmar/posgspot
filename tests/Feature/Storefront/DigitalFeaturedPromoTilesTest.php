<?php

namespace Tests\Feature\Storefront;

use App\Services\Storefront\Homepage\HomepageSectionService;
use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Accounts featured games proxy + homepage promo_tiles enrichment.
 */
class DigitalFeaturedPromoTilesTest extends TestCase
{
    protected int $businessId = 1;

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

    public function test_featured_endpoint_returns_both_platforms_and_tiles(): void
    {
        Http::fake([
            'accounts.test/api/games/featured*' => Http::response([
                'count' => 2,
                '4' => [
                    [
                        'id' => 10,
                        'title' => 'PS4 Hit',
                        'image_url' => 'assets/ps4/hit.webp',
                        'types' => [
                            'primary' => ['available' => true, 'stock' => 1, 'price' => 100],
                            'secondary' => ['available' => false, 'stock' => 0, 'price' => 0],
                            'full' => ['available' => false, 'stock' => 0, 'price' => 0],
                        ],
                    ],
                ],
                '5' => [
                    [
                        'id' => 20,
                        'title' => 'PS5 Hit',
                        'image_url' => 'assets/ps5/hit.webp',
                        'types' => [
                            'primary' => ['available' => true, 'stock' => 2, 'price' => 200],
                            'secondary' => ['available' => true, 'stock' => 1, 'price' => 150],
                            'full' => ['available' => false, 'stock' => 0, 'price' => 0],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/storefront/v1/digital/games/featured?count=2')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.games.5.0.id', 20)
            ->assertJsonPath('data.games.4.0.id', 10);

        $tiles = $response->json('data.tiles');
        $this->assertIsArray($tiles);
        $this->assertCount(2, $tiles);
        // PS5 first, then PS4.
        $this->assertSame(20, $tiles[0]['game_id']);
        $this->assertSame('5', $tiles[0]['platform']);
        $this->assertSame('/games/20?platform=5', $tiles[0]['href']);
        $this->assertSame(10, $tiles[1]['game_id']);
        $this->assertSame('/games/10?platform=4', $tiles[1]['href']);

        Http::assertSent(function ($request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_contains($request->url(), '/api/games/featured')
                && (int) ($query['count'] ?? 0) === 2
                && ($query['product_type'] ?? null) === 'game';
        });
    }

    public function test_homepage_promo_tiles_use_featured_games(): void
    {
        Http::fake([
            'accounts.test/api/games/featured*' => Http::response([
                'count' => 1,
                '4' => [],
                '5' => [
                    [
                        'id' => 42,
                        'title' => 'Horizon',
                        'image_url' => '/uploads/horizon.webp',
                        'types' => [
                            'primary' => ['available' => true, 'stock' => 1, 'price' => 300],
                            'secondary' => ['available' => false, 'stock' => 0, 'price' => 0],
                            'full' => ['available' => false, 'stock' => 0, 'price' => 0],
                        ],
                    ],
                ],
            ], 200),
        ]);

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'digital' => ['enabled' => true],
            'homepage_sections' => [
                [
                    'id' => 'sec_promo_tiles',
                    'type' => 'promo_tiles',
                    'enabled' => true,
                    'settings' => ['count' => 1],
                ],
            ],
        ]);
        Cache::flush();

        $response = $this->getJson('/api/storefront/v1/homepage')
            ->assertOk()
            ->assertJsonPath('success', true);

        $section = collect($response->json('data.sections'))
            ->firstWhere('type', 'promo_tiles');
        $this->assertNotNull($section);
        $this->assertSame(1, $section['settings']['count']);
        $this->assertCount(1, $section['settings']['tiles']);
        $this->assertSame(42, $section['settings']['tiles'][0]['game_id']);
        $this->assertSame('5', $section['settings']['tiles'][0]['platform']);
        $this->assertSame('/games/42?platform=5', $section['settings']['tiles'][0]['href']);
        $this->assertStringContainsString('horizon.webp', $section['settings']['tiles'][0]['image_url']);
    }

    public function test_promo_tiles_normalize_stores_count_only(): void
    {
        $normalized = app(HomepageSectionService::class)->normalizeSections([
            [
                'id' => 'sec_promo_tiles',
                'type' => 'promo_tiles',
                'enabled' => true,
                'settings' => [
                    'count' => 6,
                    'tiles' => [
                        ['id' => 'legacy', 'image' => 'x.jpg', 'url' => '', 'href' => '/products'],
                    ],
                ],
            ],
        ]);

        $this->assertSame(6, $normalized[0]['settings']['count']);
        $this->assertArrayNotHasKey('tiles', $normalized[0]['settings']);
    }
}
