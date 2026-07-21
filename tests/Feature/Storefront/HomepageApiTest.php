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
}