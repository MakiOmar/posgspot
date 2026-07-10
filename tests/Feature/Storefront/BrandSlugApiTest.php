<?php

namespace Tests\Feature\Storefront;

use App\Brands;
use App\BusinessLocation;
use App\Services\Storefront\StorefrontSettingService;
use Tests\TestCase;

/**
 * Storefront brand slug API (GET /brands, /brands/{slug}, products?brand_slug=).
 */
class BrandSlugApiTest extends TestCase
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
        ]);
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function test_brands_index_returns_list(): void
    {
        $response = $this->getJson('/api/storefront/v1/brands');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data']);
    }

    public function test_brand_show_returns_brand_by_slug(): void
    {
        $brand = Brands::where('business_id', $this->businessId)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->first();

        if (empty($brand)) {
            $this->markTestSkipped('No brand with slug in database.');
        }

        $response = $this->getJson('/api/storefront/v1/brands/'.$brand->slug);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $brand->id)
            ->assertJsonPath('data.name', $brand->name)
            ->assertJsonPath('data.slug', $brand->slug);
    }

    public function test_brand_show_returns_404_for_unknown_slug(): void
    {
        $response = $this->getJson('/api/storefront/v1/brands/does-not-exist-'.uniqid());

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_products_can_be_filtered_by_brand_slug(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->first();
        if (empty($location)) {
            $this->markTestSkipped('No business location in database.');
        }

        $brand = Brands::where('business_id', $this->businessId)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->first();

        if (empty($brand)) {
            $this->markTestSkipped('No brand with slug in database.');
        }

        $response = $this->getJson(
            '/api/storefront/v1/products?brand_slug='.urlencode($brand->slug).'&per_page=1'
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta' => ['total', 'current_page']]);
    }

    public function test_products_return_empty_when_brand_slug_unknown(): void
    {
        $response = $this->getJson(
            '/api/storefront/v1/products?brand_slug=unknown-'.uniqid()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 0);
    }
}
