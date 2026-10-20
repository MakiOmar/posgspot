<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Product;
use App\Services\Storefront\Shipping\ShippingLegacyMigrator;
use App\Services\Storefront\Shipping\ShippingQuoteService;
use App\Services\Storefront\StorefrontSettingService;
use App\StorefrontShippingClass;
use App\User;
use App\Variation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Products in a shipping class with requires_shipping = false skip shipping rates and address checks at checkout.
 */
class NoShippingClassTest extends TestCase
{
    use DatabaseTransactions;

    protected int $businessId = 1;

    private ?BusinessLocation $location = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->location = BusinessLocation::where('business_id', $this->businessId)->first();
        if (empty($this->location)) {
            return;
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$this->location->id],
            'shipping' => [
                'flat_rate' => 40,
                'free_shipping_threshold' => 100000,
            ],
        ]);
        Cache::flush();
        app(ShippingLegacyMigrator::class)->ensureDefaultZones($this->businessId);
    }

    public function test_cart_of_only_no_shipping_products_needs_no_shipping_or_address(): void
    {
        [$noShipVariation] = $this->variations(1);
        $this->assignClass($noShipVariation, $this->createClass(false));

        $quotes = app(ShippingQuoteService::class);
        $items = [['variation_id' => $noShipVariation->id, 'quantity' => 2]];

        $quoted = $quotes->quote($this->businessId, 300, $items, [], null, null, 'en', false);

        $this->assertFalse($quoted['shipping_required']);
        $this->assertTrue($quoted['digital_only']);
        $this->assertCount(1, $quoted['available_rates']);
        $this->assertSame('No shipping required', $quoted['available_rates'][0]['title']);
        $this->assertEqualsWithDelta(0.0, (float) $quoted['shipping'], 0.0001);

        $resolved = $quotes->resolveSelectedRate($this->businessId, 300, $items, [], null, null, 'en', false);
        $this->assertTrue($resolved['ok']);
        $this->assertEqualsWithDelta(0.0, (float) $resolved['shipping'], 0.0001);
    }

    public function test_cart_validate_api_reports_shipping_not_required(): void
    {
        [$noShipVariation] = $this->variations(1);
        $this->assignClass($noShipVariation, $this->createClass(false));

        $this->postJson('/api/storefront/v1/cart/validate', [
            'items' => [['variation_id' => $noShipVariation->id, 'quantity' => 1]],
            'location_id' => $this->location->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.shipping_required', false)
            ->assertJsonPath('data.digital_only', true)
            ->assertJsonPath('data.shipping', 0)
            ->assertJsonPath('data.available_rates.0.title', 'No shipping required');
    }

    public function test_mixed_cart_still_ships_and_ignores_no_shipping_lines(): void
    {
        [$noShipVariation, $shippedVariation] = $this->variations(2);
        $this->assignClass($noShipVariation, $this->createClass(false));
        $this->assignClass($shippedVariation, null);

        $quoted = app(ShippingQuoteService::class)->quote(
            $this->businessId,
            300,
            [
                ['variation_id' => $noShipVariation->id, 'quantity' => 1],
                ['variation_id' => $shippedVariation->id, 'quantity' => 1],
            ],
            ['country' => 'EG', 'state' => 'C'],
            null,
            null,
            'en',
            false
        );

        $this->assertTrue($quoted['shipping_required']);
        $this->assertFalse($quoted['digital_only']);
        $this->assertNotEmpty($quoted['available_rates']);
    }

    public function test_class_that_requires_shipping_keeps_normal_rates(): void
    {
        [$variation] = $this->variations(1);
        $this->assignClass($variation, $this->createClass(true));

        $quoted = app(ShippingQuoteService::class)->quote(
            $this->businessId,
            300,
            [['variation_id' => $variation->id, 'quantity' => 1]],
            ['country' => 'EG', 'state' => 'C'],
            null,
            null,
            'en',
            false
        );

        $this->assertTrue($quoted['shipping_required']);
        $this->assertFalse($quoted['digital_only']);
    }

    public function test_admin_can_toggle_requires_shipping(): void
    {
        $user = User::where('business_id', $this->businessId)->first();
        if (empty($user) || ! $user->can('storefront.settings')) {
            $this->markTestSkipped('No storefront settings admin in database.');
        }
        $class = $this->createClass(true);

        $this->actingAs($user)
            ->withSession(['user.business_id' => $this->businessId])
            ->putJson('/storefront/shipping/classes/'.$class->id, ['requires_shipping' => false])
            ->assertOk()
            ->assertJsonPath('data.requires_shipping', false);

        $this->assertFalse((bool) $class->fresh()->requires_shipping);
    }

    private function createClass(bool $requiresShipping): StorefrontShippingClass
    {
        return StorefrontShippingClass::create([
            'business_id' => $this->businessId,
            'name' => 'Test class '.uniqid(),
            'slug' => 'test-class-'.uniqid(),
            'requires_shipping' => $requiresShipping,
        ]);
    }

    private function assignClass(Variation $variation, ?StorefrontShippingClass $class): void
    {
        Product::where('id', $variation->product_id)->update(['shipping_class_id' => $class?->id]);
    }

    /**
     * @return list<Variation>
     */
    private function variations(int $count): array
    {
        if (empty($this->location)) {
            $this->markTestSkipped('No business location in database.');
        }

        $variations = Variation::whereHas('product', fn ($q) => $q->where('business_id', $this->businessId)
            ->where('type', 'single'))
            ->get()
            ->unique('product_id')
            ->take($count)
            ->values()
            ->all();

        if (count($variations) < $count) {
            $this->markTestSkipped('Not enough products in database.');
        }

        return $variations;
    }
}
