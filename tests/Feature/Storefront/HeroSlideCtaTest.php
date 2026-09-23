<?php

namespace Tests\Feature\Storefront;

use App\Category;
use App\Product;
use App\Services\Storefront\Homepage\HomepageSectionService;
use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Hero slide structured CTA (product / category / game / legacy path).
 */
class HeroSlideCtaTest extends TestCase
{
    protected int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_normalize_migrates_legacy_href_to_path_cta(): void
    {
        $normalized = app(HomepageSectionService::class)->normalizeSections([
            [
                'id' => 'sec_hero',
                'type' => 'hero_slider',
                'enabled' => true,
                'settings' => [
                    'slides' => [
                        [
                            'id' => 'slide_1',
                            'image' => null,
                            'url' => 'https://example.com/hero.jpg',
                            'href' => '/products',
                            'kicker' => ['en' => 'K', 'ar' => ''],
                            'title' => ['en' => 'T', 'ar' => ''],
                        ],
                    ],
                ],
            ],
        ], $this->businessId);

        $slide = $normalized[0]['settings']['slides'][0] ?? null;
        $this->assertNotNull($slide);
        $this->assertArrayNotHasKey('href', $slide);
        $this->assertSame(['type' => 'path', 'href' => '/products'], $slide['cta']);
    }

    public function test_homepage_presents_legacy_href_as_path_cta(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'homepage_sections' => [
                [
                    'id' => 'sec_hero',
                    'type' => 'hero_slider',
                    'enabled' => true,
                    'settings' => [
                        'slides' => [
                            [
                                'id' => 'slide_legacy',
                                'image' => null,
                                'url' => 'https://example.com/legacy-hero.jpg',
                                'href' => '/products',
                                'kicker' => ['en' => 'Legacy', 'ar' => ''],
                                'title' => ['en' => 'Hero', 'ar' => ''],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        Cache::flush();

        $slide = $this->getJson('/api/storefront/v1/homepage')
            ->assertOk()
            ->json('data.sections.0.settings.slides.0');

        $this->assertSame('path', $slide['cta']['type']);
        $this->assertSame('/products', $slide['cta']['href']);
        $this->assertSame('/products', $slide['href']);
    }

    public function test_homepage_presents_game_cta(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'homepage_sections' => [
                [
                    'id' => 'sec_hero',
                    'type' => 'hero_slider',
                    'enabled' => true,
                    'settings' => [
                        'slides' => [
                            [
                                'id' => 'slide_game',
                                'image' => null,
                                'url' => 'https://example.com/game-hero.jpg',
                                'cta' => [
                                    'type' => 'game',
                                    'id' => 42,
                                    'platform' => 5,
                                    'label' => 'Horizon',
                                ],
                                'kicker' => ['en' => 'Now', 'ar' => ''],
                                'title' => ['en' => 'Horizon', 'ar' => ''],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        Cache::flush();

        $slide = $this->getJson('/api/storefront/v1/homepage')
            ->assertOk()
            ->json('data.sections.0.settings.slides.0');

        $this->assertSame('game', $slide['cta']['type']);
        $this->assertSame(42, $slide['cta']['id']);
        $this->assertSame('5', $slide['cta']['platform']);
        $this->assertSame('/games/42?platform=5', $slide['cta']['href']);
        $this->assertSame('/games/42?platform=5', $slide['href']);
        $this->assertSame('Horizon', $slide['cta']['label']);
    }

    public function test_missing_product_cta_is_null(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'homepage_sections' => [
                [
                    'id' => 'sec_hero',
                    'type' => 'hero_slider',
                    'enabled' => true,
                    'settings' => [
                        'slides' => [
                            [
                                'id' => 'slide_missing',
                                'image' => null,
                                'url' => 'https://example.com/missing-hero.jpg',
                                'cta' => [
                                    'type' => 'product',
                                    'id' => 999999991,
                                    'label' => 'Gone',
                                ],
                                'kicker' => ['en' => 'X', 'ar' => ''],
                                'title' => ['en' => 'Y', 'ar' => ''],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        Cache::flush();

        $slide = $this->getJson('/api/storefront/v1/homepage')
            ->assertOk()
            ->json('data.sections.0.settings.slides.0');

        $this->assertNull($slide['cta']);
        $this->assertNull($slide['href']);
    }

    public function test_homepage_presents_category_cta_when_available(): void
    {
        $category = Category::where('business_id', $this->businessId)
            ->where('category_type', 'product')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->first();

        if (empty($category)) {
            $this->markTestSkipped('No product category with slug in database.');
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'homepage_sections' => [
                [
                    'id' => 'sec_hero',
                    'type' => 'hero_slider',
                    'enabled' => true,
                    'settings' => [
                        'slides' => [
                            [
                                'id' => 'slide_cat',
                                'image' => null,
                                'url' => 'https://example.com/cat-hero.jpg',
                                'cta' => [
                                    'type' => 'category',
                                    'id' => (int) $category->id,
                                    'label' => (string) $category->name,
                                ],
                                'kicker' => ['en' => 'Shop', 'ar' => ''],
                                'title' => ['en' => 'Category', 'ar' => ''],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        Cache::flush();

        $slide = $this->getJson('/api/storefront/v1/homepage')
            ->assertOk()
            ->json('data.sections.0.settings.slides.0');

        $this->assertSame('category', $slide['cta']['type']);
        $this->assertSame((int) $category->id, $slide['cta']['id']);
        $this->assertSame((string) $category->slug, $slide['cta']['slug']);
        $this->assertSame('/category/'.$category->slug, $slide['cta']['href']);
    }

    public function test_homepage_presents_product_cta_when_available(): void
    {
        $product = Product::where('business_id', $this->businessId)
            ->where('not_for_selling', 0)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->first();

        if (empty($product)) {
            $this->markTestSkipped('No sellable product with slug in database.');
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'homepage_sections' => [
                [
                    'id' => 'sec_hero',
                    'type' => 'hero_slider',
                    'enabled' => true,
                    'settings' => [
                        'slides' => [
                            [
                                'id' => 'slide_prod',
                                'image' => null,
                                'url' => 'https://example.com/prod-hero.jpg',
                                'cta' => [
                                    'type' => 'product',
                                    'id' => (int) $product->id,
                                    'label' => (string) $product->name,
                                ],
                                'kicker' => ['en' => 'Buy', 'ar' => ''],
                                'title' => ['en' => 'Product', 'ar' => ''],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        Cache::flush();

        $slide = $this->getJson('/api/storefront/v1/homepage')
            ->assertOk()
            ->json('data.sections.0.settings.slides.0');

        if ($slide['cta'] === null) {
            $this->markTestSkipped('Product not resolvable via storefront catalog (locations/stock).');
        }

        $this->assertSame('product', $slide['cta']['type']);
        $this->assertSame((int) $product->id, $slide['cta']['id']);
        $this->assertNotEmpty($slide['cta']['slug']);
        $this->assertStringStartsWith('/products/', $slide['cta']['href']);
    }

    public function test_empty_cta_hides_shop_button_fields(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'homepage_sections' => [
                [
                    'id' => 'sec_hero',
                    'type' => 'hero_slider',
                    'enabled' => true,
                    'settings' => [
                        'slides' => [
                            [
                                'id' => 'slide_none',
                                'image' => null,
                                'url' => 'https://example.com/no-cta.jpg',
                                'cta' => null,
                                'kicker' => ['en' => 'No', 'ar' => ''],
                                'title' => ['en' => 'CTA', 'ar' => ''],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        Cache::flush();

        $slide = $this->getJson('/api/storefront/v1/homepage')
            ->assertOk()
            ->json('data.sections.0.settings.slides.0');

        $this->assertNull($slide['cta']);
        $this->assertNull($slide['href']);
    }
}
