<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Product;
use App\Services\Storefront\StorefrontSettingService;
use App\Variation;
use Tests\TestCase;

/**
 * Availability reports stock across ALL active business locations,
 * independent of the public storefront selling-location selection.
 */
class AvailabilityLocationsTest extends TestCase
{
    protected int $businessId = 1;

    public function test_availability_includes_locations_outside_public_selling_set(): void
    {
        $activeLocationCount = BusinessLocation::where('business_id', $this->businessId)
            ->where('is_active', 1)
            ->count();

        if ($activeLocationCount === 0) {
            $this->markTestSkipped('No active business location in database.');
        }

        // Clear the public selling locations entirely: previously this made
        // availability return zero locations. It must now still list them all.
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        [$product, $variation] = $this->firstSellableProduct();
        if ($product === null) {
            $this->markTestSkipped('No sellable product/variation in database.');
        }

        $response = $this->getJson(
            '/api/storefront/v1/products/'.$product->id.'/availability?variation_id='.$variation->id
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount($activeLocationCount, 'data.locations');
    }

    public function test_availability_rows_expose_in_stock_flag_for_every_location(): void
    {
        $activeLocationCount = BusinessLocation::where('business_id', $this->businessId)
            ->where('is_active', 1)
            ->count();

        if ($activeLocationCount === 0) {
            $this->markTestSkipped('No active business location in database.');
        }

        [$product, $variation] = $this->firstSellableProduct();
        if ($product === null) {
            $this->markTestSkipped('No sellable product/variation in database.');
        }

        $response = $this->getJson(
            '/api/storefront/v1/products/'.$product->id.'/availability?variation_id='.$variation->id
        );

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'in_stock_count',
                    'locations' => [
                        ['location_id', 'name', 'in_stock', 'qty_available', 'latitude', 'longitude', 'maps_url'],
                    ],
                ],
            ]);
    }

    /**
     * @return array{0: ?Product, 1: ?Variation}
     */
    private function firstSellableProduct(): array
    {
        $product = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->first();

        if (empty($product)) {
            return [null, null];
        }

        $variation = Variation::where('product_id', $product->id)
            ->whereNull('deleted_at')
            ->first();

        return [$product, $variation];
    }
}
