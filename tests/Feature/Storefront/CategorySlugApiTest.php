<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Category;
use App\Services\Storefront\StorefrontSettingService;
use Tests\TestCase;

/**
 * Storefront category slug API (GET /categories/{slug}, products?category_slug=).
 */
class CategorySlugApiTest extends TestCase
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

    public function test_category_show_returns_category_by_slug(): void
    {
        $category = Category::where('business_id', $this->businessId)
            ->where('category_type', 'product')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->first();

        if (empty($category)) {
            $this->markTestSkipped('No product category with slug in database.');
        }

        $response = $this->getJson('/api/storefront/v1/categories/'.$category->slug);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.name', $category->name)
            ->assertJsonPath('data.slug', $category->slug);
    }

    public function test_category_show_returns_404_for_unknown_slug(): void
    {
        $response = $this->getJson('/api/storefront/v1/categories/does-not-exist-'.uniqid());

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_products_can_be_filtered_by_category_slug(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->first();
        if (empty($location)) {
            $this->markTestSkipped('No business location in database.');
        }

        $category = Category::where('business_id', $this->businessId)
            ->where('category_type', 'product')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->first();

        if (empty($category)) {
            $this->markTestSkipped('No product category with slug in database.');
        }

        $response = $this->getJson(
            '/api/storefront/v1/products?category_slug='.urlencode($category->slug).'&per_page=1'
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta' => ['total', 'current_page']]);
    }

    public function test_products_return_empty_when_category_slug_unknown(): void
    {
        $response = $this->getJson(
            '/api/storefront/v1/products?category_slug=unknown-'.uniqid()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 0);
    }
}
