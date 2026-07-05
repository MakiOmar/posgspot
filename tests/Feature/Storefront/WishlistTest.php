<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Product;
use App\Services\Storefront\StorefrontSettingService;
use App\StorefrontWishlistItem;
use Tests\TestCase;

/**
 * Storefront wishlist API (list, add, remove, merge).
 */
class WishlistTest extends TestCase
{
    protected int $businessId = 1;

    private function registerAndLogin(): array
    {
        $email = 'wishlist_test_'.uniqid().'@example.com';
        $mobile = '+2010'.random_int(10000000, 99999999);

        $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Wish',
            'last_name' => 'Tester',
            'email' => $email,
            'mobile' => $mobile,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $login = $this->postJson('/api/storefront/v1/auth/login', [
            'login' => $email,
            'password' => 'password123',
        ])->assertOk();

        return [
            'token' => $login->json('data.token'),
            'contact_id' => (int) $login->json('data.contact.id'),
        ];
    }

    private function sellableProductId(): ?int
    {
        $location = BusinessLocation::where('business_id', $this->businessId)
            ->where('is_active', 1)
            ->first();

        if (empty($location)) {
            return null;
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $product = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->whereHas('product_locations', fn ($q) => $q->where('product_locations.location_id', $location->id))
            ->first();

        return $product?->id;
    }

    public function test_wishlist_requires_auth(): void
    {
        $this->getJson('/api/storefront/v1/wishlist')->assertUnauthorized();
    }

    public function test_customer_can_add_list_and_remove_wishlist_items(): void
    {
        $productId = $this->sellableProductId();
        if (empty($productId)) {
            $this->markTestSkipped('No sellable product in database.');
        }

        $session = $this->registerAndLogin();

        $this->withToken($session['token'])
            ->getJson('/api/storefront/v1/wishlist')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.count', 0)
            ->assertJsonPath('data.items', []);

        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/wishlist', ['product_id' => $productId])
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.items.0.id', $productId);

        $this->assertDatabaseHas('storefront_wishlist_items', [
            'business_id' => $this->businessId,
            'contact_id' => $session['contact_id'],
            'product_id' => $productId,
        ]);

        $this->withToken($session['token'])
            ->deleteJson("/api/storefront/v1/wishlist/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.count', 0);

        $this->assertDatabaseMissing('storefront_wishlist_items', [
            'contact_id' => $session['contact_id'],
            'product_id' => $productId,
        ]);
    }

    public function test_wishlist_merge_deduplicates_guest_product_ids(): void
    {
        $productId = $this->sellableProductId();
        if (empty($productId)) {
            $this->markTestSkipped('No sellable product in database.');
        }

        $session = $this->registerAndLogin();

        StorefrontWishlistItem::create([
            'business_id' => $this->businessId,
            'contact_id' => $session['contact_id'],
            'product_id' => $productId,
        ]);

        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/wishlist/merge', [
                'product_ids' => [$productId, $productId],
            ])
            ->assertOk()
            ->assertJsonPath('data.count', 1);

        $this->assertSame(
            1,
            StorefrontWishlistItem::where('contact_id', $session['contact_id'])
                ->where('product_id', $productId)
                ->count()
        );
    }
}
