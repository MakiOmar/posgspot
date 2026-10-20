<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A selling (online fulfillment) location hidden from branch lists must still be offered to checkout.
 */
class HiddenSellingLocationTest extends TestCase
{
    use DatabaseTransactions;

    protected int $businessId = 1;

    public function test_hidden_selling_location_is_listed_for_checkout_only(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)
            ->where('is_active', 1)
            ->first();

        if (! $location) {
            $this->markTestSkipped('No active business location in database.');
        }

        $location->show_on_storefront = false;
        $location->save();
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
        ]);
        Cache::flush();

        $sellingIds = collect($this->getJson('/api/storefront/v1/locations?selling_only=1')
            ->assertOk()
            ->json('data'))->pluck('id');
        $this->assertTrue($sellingIds->contains($location->id));

        $branchIds = collect($this->getJson('/api/storefront/v1/locations')
            ->assertOk()
            ->json('data'))->pluck('id');
        $this->assertFalse($branchIds->contains($location->id));
    }

    public function test_inactive_selling_location_is_not_offered_to_checkout(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->first();

        if (! $location) {
            $this->markTestSkipped('No business location in database.');
        }

        $location->is_active = 0;
        $location->save();
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
        ]);
        Cache::flush();

        $this->getJson('/api/storefront/v1/locations?selling_only=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
