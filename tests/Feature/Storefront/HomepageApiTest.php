<?php

namespace Tests\Feature\Storefront;

use App\Services\Storefront\Homepage\HomepageSectionService;
use App\Services\Storefront\StorefrontSettingService;
use App\StorefrontSetting;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Homepage section composition API.
 */
class HomepageApiTest extends TestCase
{
    protected int $businessId = 1;

    public function test_homepage_returns_seeded_sections_when_empty(): void
    {
        StorefrontSetting::updateOrCreate(
            ['business_id' => $this->businessId],
            ['value' => ['selling_location_ids' => [1], 'homepage_sections' => []]]
        );
        Cache::flush();

        $response = $this->getJson('/api/storefront/v1/homepage');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['sections']]);

        $sections = $response->json('data.sections');
        $this->assertIsArray($sections);
        $this->assertNotEmpty($sections);
        $this->assertSame('hero_slider', $sections[0]['type']);
        $this->assertArrayHasKey('slides', $sections[0]['settings']);
        $this->assertNotEmpty($sections[0]['settings']['slides']);
        $this->assertArrayHasKey('image_url', $sections[0]['settings']['slides'][0]);
    }

    public function test_homepage_omits_disabled_sections(): void
    {
        $defaults = app(HomepageSectionService::class)->defaultSections();
        $defaults[0]['enabled'] = false;

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'homepage_sections' => $defaults,
        ]);
        Cache::flush();

        $response = $this->getJson('/api/storefront/v1/homepage');
        $response->assertOk();

        $types = array_column($response->json('data.sections'), 'type');
        $this->assertNotContains('hero_slider', $types);
        $this->assertContains('promo_tiles', $types);
    }

    public function test_homepage_rejects_unknown_section_types_on_save(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'homepage_sections' => [
                [
                    'id' => 'sec_ok',
                    'type' => 'bestsellers',
                    'enabled' => true,
                    'settings' => ['per_page' => 4, 'in_stock_only' => false],
                ],
                [
                    'id' => 'sec_bad',
                    'type' => 'not_a_real_type',
                    'enabled' => true,
                    'settings' => [],
                ],
            ],
        ]);
        Cache::flush();

        $response = $this->getJson('/api/storefront/v1/homepage');
        $types = array_column($response->json('data.sections'), 'type');
        $this->assertSame(['bestsellers'], $types);
        $this->assertSame(4, $response->json('data.sections.0.settings.per_page'));
        $this->assertFalse($response->json('data.sections.0.settings.in_stock_only'));
    }

    public function test_bestsellers_style_horizontal_is_persisted(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'homepage_sections' => [
                [
                    'id' => 'sec_best',
                    'type' => 'bestsellers',
                    'enabled' => true,
                    'settings' => [
                        'per_page' => 6,
                        'in_stock_only' => true,
                        'style' => 'horizontal',
                    ],
                ],
            ],
        ]);
        Cache::flush();

        $response = $this->getJson('/api/storefront/v1/homepage');
        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'bestsellers')
            ->assertJsonPath('data.sections.0.settings.style', 'horizontal');
    }

    public function test_category_shelf_section_without_category_is_omitted(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'homepage_sections' => [
                [
                    'id' => 'sec_cat',
                    'type' => 'category_shelf',
                    'enabled' => true,
                    'settings' => ['category_id' => null, 'products_per_shelf' => 6],
                ],
                [
                    'id' => 'sec_best',
                    'type' => 'bestsellers',
                    'enabled' => true,
                    'settings' => ['per_page' => 3, 'in_stock_only' => true],
                ],
            ],
        ]);
        Cache::flush();

        $response = $this->getJson('/api/storefront/v1/homepage');
        $response->assertOk();
        $types = array_column($response->json('data.sections'), 'type');
        $this->assertNotContains('category_shelf', $types);
        $this->assertContains('bestsellers', $types);
    }

    public function test_promo_banner_section_without_content_is_omitted(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'homepage_sections' => [
                [
                    'id' => 'sec_bn',
                    'type' => 'promo_banner',
                    'enabled' => true,
                    'settings' => [
                        'background_color' => '#f5a623',
                        'top_title' => ['en' => '', 'ar' => ''],
                        'main_title' => ['en' => '', 'ar' => ''],
                    ],
                ],
                [
                    'id' => 'sec_best',
                    'type' => 'bestsellers',
                    'enabled' => true,
                    'settings' => ['per_page' => 3, 'in_stock_only' => true],
                ],
            ],
        ]);
        Cache::flush();

        $response = $this->getJson('/api/storefront/v1/homepage');
        $response->assertOk();
        $types = array_column($response->json('data.sections'), 'type');
        $this->assertNotContains('promo_banner', $types);
        $this->assertContains('bestsellers', $types);
    }

    public function test_promo_banner_section_presents_compositional_fields(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'homepage_sections' => [
                [
                    'id' => 'sec_bn',
                    'type' => 'promo_banner',
                    'enabled' => true,
                    'settings' => [
                        'logo' => ['image' => null, 'url' => 'https://example.com/logo.png'],
                        'top_title' => ['en' => 'Feel the power', 'ar' => 'اشعر بالقوة'],
                        'main_title' => ['en' => 'Portal Remote Player', 'ar' => 'جهاز بورتال'],
                        'background_color' => '#f5a623',
                        'border_radius' => 16,
                        'border_thickness' => 0,
                        'image' => [
                            'image' => null,
                            'url' => 'https://example.com/portal.png',
                            'position' => [
                                'top' => '-12%',
                                'right' => '2%',
                                'bottom' => 'auto',
                                'left' => 'auto',
                                'width' => '42%',
                            ],
                        ],
                        'button' => [
                            'label' => ['en' => 'Shop Now', 'ar' => 'تسوق الآن'],
                            'link' => '/products',
                            'background_color' => '#ffffff',
                            'text_color' => '#111111',
                            'show_arrow' => true,
                        ],
                    ],
                ],
            ],
        ]);
        Cache::flush();

        $response = $this->getJson('/api/storefront/v1/homepage');
        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'promo_banner')
            ->assertJsonPath('data.sections.0.settings.logo_url', 'https://example.com/logo.png')
            ->assertJsonPath('data.sections.0.settings.image_url', 'https://example.com/portal.png')
            ->assertJsonPath('data.sections.0.settings.top_title', 'Feel the power')
            ->assertJsonPath('data.sections.0.settings.main_title', 'Portal Remote Player')
            ->assertJsonPath('data.sections.0.settings.background_color', '#f5a623')
            ->assertJsonPath('data.sections.0.settings.button.link', '/products')
            ->assertJsonPath('data.sections.0.settings.button.label', 'Shop Now')
            ->assertJsonPath('data.sections.0.settings.image_position.width', '42%');
    }

    public function test_video_section_presents_youtube_embed(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'homepage_sections' => [
                [
                    'id' => 'sec_vid',
                    'type' => 'video',
                    'enabled' => true,
                    'settings' => [
                        'source' => 'youtube',
                        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'poster' => '',
                        'title' => ['en' => 'Trailer', 'ar' => 'إعلان'],
                    ],
                ],
            ],
        ]);
        Cache::flush();

        $response = $this->getJson('/api/storefront/v1/homepage');
        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'video')
            ->assertJsonPath('data.sections.0.settings.source', 'youtube')
            ->assertJsonPath('data.sections.0.settings.embed_url', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
            ->assertJsonPath('data.sections.0.settings.title', 'Trailer');
    }

    public function test_video_section_without_url_is_omitted(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'homepage_sections' => [
                [
                    'id' => 'sec_vid',
                    'type' => 'video',
                    'enabled' => true,
                    'settings' => [
                        'source' => 'vimeo',
                        'url' => '',
                        'title' => ['en' => 'Empty', 'ar' => ''],
                    ],
                ],
                [
                    'id' => 'sec_best',
                    'type' => 'bestsellers',
                    'enabled' => true,
                    'settings' => ['per_page' => 3, 'in_stock_only' => true],
                ],
            ],
        ]);
        Cache::flush();

        $response = $this->getJson('/api/storefront/v1/homepage');
        $types = array_column($response->json('data.sections'), 'type');
        $this->assertNotContains('video', $types);
        $this->assertContains('bestsellers', $types);
    }

    public function test_trust_badges_section_presents_items(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'homepage_sections' => [
                [
                    'id' => 'sec_trust',
                    'type' => 'trust_badges',
                    'enabled' => true,
                    'settings' => [
                        'items' => [
                            [
                                'id' => 'badge_1',
                                'image' => null,
                                'url' => 'https://example.com/ship.svg',
                                'title' => ['en' => 'Worldwide Shipping', 'ar' => 'شحن عالمي'],
                                'description' => ['en' => 'Enjoy free delivery on every order.', 'ar' => 'توصيل مجاني'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        Cache::flush();

        $response = $this->getJson('/api/storefront/v1/homepage');
        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'trust_badges')
            ->assertJsonPath('data.sections.0.settings.items.0.title', 'Worldwide Shipping')
            ->assertJsonPath('data.sections.0.settings.items.0.description', 'Enjoy free delivery on every order.')
            ->assertJsonPath('data.sections.0.settings.items.0.icon_url', 'https://example.com/ship.svg');
    }

    public function test_trust_badges_section_without_items_is_omitted(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [1],
            'homepage_sections' => [
                [
                    'id' => 'sec_trust',
                    'type' => 'trust_badges',
                    'enabled' => true,
                    'settings' => ['items' => []],
                ],
                [
                    'id' => 'sec_best',
                    'type' => 'bestsellers',
                    'enabled' => true,
                    'settings' => ['per_page' => 3, 'in_stock_only' => true],
                ],
            ],
        ]);
        Cache::flush();

        $response = $this->getJson('/api/storefront/v1/homepage');
        $types = array_column($response->json('data.sections'), 'type');
        $this->assertNotContains('trust_badges', $types);
        $this->assertContains('bestsellers', $types);
    }
}