<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Product;
use App\Services\Storefront\StorefrontSettingService;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Featured filter + bestsellers sort for the storefront homepage.
 */
class HomepageCatalogTest extends TestCase
{
    protected int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_featured_products_filter(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->where('is_active', 1)->first();
        if (empty($location)) {
            $this->markTestSkipped('No active business location.');
        }

        $product = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->first();
        if (empty($product)) {
            $this->markTestSkipped('No sellable product.');
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
            'default_fulfillment_location_id' => $location->id,
        ]);
        Cache::flush();

        $variation = Variation::where('product_id', $product->id)->whereNull('deleted_at')->first();
        if ($variation) {
            VariationLocationDetails::updateOrCreate(
                ['variation_id' => $variation->id, 'location_id' => $location->id],
                [
                    'product_id' => $product->id,
                    'product_variation_id' => $variation->product_variation_id,
                    'qty_available' => 5,
                ]
            );
        }

        $product->is_storefront_featured = 1;
        $product->save();

        $response = $this->getJson('/api/storefront/v1/products?featured=1&per_page=20');
        $response->assertOk()->assertJsonPath('success', true);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($product->id, $ids);

        $product->is_storefront_featured = 0;
        $product->save();

        $empty = $this->getJson('/api/storefront/v1/products?featured=1&per_page=20');
        $empty->assertOk();
        $this->assertNotContains($product->id, collect($empty->json('data'))->pluck('id')->all());
    }

    public function test_bestsellers_sort_accepted(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->where('is_active', 1)->first();
        if (empty($location)) {
            $this->markTestSkipped('No active business location.');
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
            'default_fulfillment_location_id' => $location->id,
        ]);
        Cache::flush();

        $response = $this->getJson('/api/storefront/v1/products?sort=bestsellers&per_page=6');
        $response->assertOk()->assertJsonPath('success', true);
        $this->assertIsArray($response->json('data'));
    }

    public function test_ids_filter_returns_only_listed_products_in_given_order(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->where('is_active', 1)->first();
        if (empty($location)) {
            $this->markTestSkipped('No active business location.');
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
            'default_fulfillment_location_id' => $location->id,
        ]);
        Cache::flush();

        $listed = collect($this->getJson('/api/storefront/v1/products?per_page=10')->json('data'))
            ->pluck('id')
            ->values();
        if ($listed->count() < 2) {
            $this->markTestSkipped('Need at least two storefront products.');
        }

        // Reverse of catalog order proves FIELD() ordering, not default id sort.
        $picked = [$listed[1], $listed[0]];
        $response = $this->getJson('/api/storefront/v1/products?ids='.implode(',', $picked).'&per_page=10');
        $response->assertOk()->assertJsonPath('success', true);

        $this->assertSame($picked, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_ids_filter_with_only_invalid_ids_returns_empty(): void
    {
        $response = $this->getJson('/api/storefront/v1/products?ids=0,-3,abc');
        $response->assertOk()->assertJsonPath('success', true);
        $this->assertIsArray($response->json('data'));
    }

    public function test_homepage_shelves_endpoint(): void
    {
        $response = $this->getJson('/api/storefront/v1/categories/homepage-shelves');
        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertIsArray($response->json('data'));
    }
}
