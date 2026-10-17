<?php

namespace Tests\Feature\Storefront;

use App\Brands;
use App\BusinessLocation;
use App\Product;
use App\Services\Storefront\StorefrontSettingService;
use App\Unit;
use App\User;
use App\Utils\ProductUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Brands.show_on_storefront hides a brand (not its products) from the storefront API,
 * and the POS brand form controls it.
 */
class BrandStorefrontVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private int $businessId = 1;

    private int $locationId;

    protected function setUp(): void
    {
        parent::setUp();

        $location = BusinessLocation::where('business_id', $this->businessId)->first();
        if (empty($location)) {
            $this->markTestSkipped('No business location in database.');
        }
        $this->locationId = (int) $location->id;

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$this->locationId],
        ]);
        Cache::flush();
    }

    private function brandWithProduct(): array
    {
        $suffix = uniqid();
        $brand = Brands::create([
            'business_id' => $this->businessId,
            'name' => 'ZZ Visibility Brand '.$suffix,
            'slug' => 'zz-visibility-brand-'.$suffix,
            'created_by' => 1,
        ]);
        $product = Product::create([
            'name' => 'ZZ Visibility Product '.$suffix,
            'slug' => 'zz-visibility-product-'.$suffix,
            'business_id' => $this->businessId,
            'brand_id' => $brand->id,
            'type' => 'single',
            'unit_id' => Unit::where('business_id', $this->businessId)->value('id'),
            'tax_type' => 'exclusive',
            'enable_stock' => 0,
            'alert_quantity' => 0,
            'sku' => 'ZZVIS-'.$suffix,
            'barcode_type' => 'C128',
            'created_by' => 1,
            'is_inactive' => 0,
            'not_for_selling' => 0,
        ]);
        app(ProductUtil::class)->createSingleProductVariation($product, $product->sku, 100, 100, 0, 100, 100);
        $product->product_locations()->sync([$this->locationId]);

        return [$brand, $product];
    }

    public function test_hidden_brand_disappears_from_storefront_but_its_products_stay(): void
    {
        [$brand, $product] = $this->brandWithProduct();

        $this->assertContains($brand->id, collect($this->getJson('/api/storefront/v1/brands')->assertOk()->json('data'))->pluck('id')->all());
        $this->getJson('/api/storefront/v1/brands/'.$brand->slug)->assertOk();
        $this->getJson('/api/storefront/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.brand.slug', $brand->slug);

        $brand->update(['show_on_storefront' => false]);

        $this->assertNotContains($brand->id, collect($this->getJson('/api/storefront/v1/brands')->assertOk()->json('data'))->pluck('id')->all());
        $this->getJson('/api/storefront/v1/brands/'.$brand->slug)->assertNotFound();
        $this->getJson('/api/storefront/v1/products?brand_slug='.$brand->slug)->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/storefront/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.brand', null);
    }

    public function test_pos_brand_form_controls_storefront_visibility(): void
    {
        $admin = User::where('business_id', $this->businessId)
            ->get()
            ->first(fn (User $user) => $user->hasRole('Admin#'.$this->businessId));
        if (! $admin) {
            $this->markTestSkipped('No admin user for business 1.');
        }
        [$brand] = $this->brandWithProduct();
        $session = ['user.business_id' => $this->businessId, 'user.id' => $admin->id];
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        $this->actingAs($admin)->withSession($session)
            ->put('/brands/'.$brand->id, ['name' => $brand->name, 'description' => '', 'storefront_visibility_field' => 1], $ajax)
            ->assertJsonPath('success', true);
        $this->assertFalse($brand->fresh()->show_on_storefront);

        $this->actingAs($admin)->withSession($session)
            ->put('/brands/'.$brand->id, ['name' => $brand->name, 'description' => '', 'storefront_visibility_field' => 1, 'show_on_storefront' => 1], $ajax)
            ->assertJsonPath('success', true);
        $this->assertTrue($brand->fresh()->show_on_storefront);

        // Older callers without the checkbox (e.g. API quick add) keep new brands visible.
        $name = 'ZZ Quick Brand '.uniqid();
        $this->actingAs($admin)->withSession($session)
            ->post('/brands', ['name' => $name], $ajax)
            ->assertJsonPath('success', true);
        $this->assertTrue(Brands::where('business_id', $this->businessId)->where('name', $name)->firstOrFail()->show_on_storefront);
    }
}
