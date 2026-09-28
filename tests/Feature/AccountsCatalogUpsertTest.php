<?php

namespace Tests\Feature;

use App\Brands;
use App\Business;
use App\Category;
use App\Product;
use App\Services\AccountsCatalogService;
use App\User;
use App\Variation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Accounts pushes one hidden POS product per offer; sales then reference that product id.
 */
class AccountsCatalogUpsertTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $business = Business::with('owner')->find(1);
        if (! $business || ! $business->owner) {
            $this->markTestSkipped('Business 1 with an owner is required.');
        }
        $this->owner = $business->owner;
        $this->usePassportTestKeys();
    }

    /**
     * Local installs may not have run passport:keys; the guard only needs a valid pair.
     */
    private function usePassportTestKeys(): void
    {
        // Windows PHP builds often ship without openssl.cnf; a minimal file is enough for keygen.
        $cnf = tempnam(sys_get_temp_dir(), 'ossl');
        file_put_contents($cnf, "[req]\ndistinguished_name=req_dn\n[req_dn]\n");
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $cnf];
        $key = openssl_pkey_new($options);
        if ($key === false) {
            @unlink($cnf);
            $this->markTestSkipped('OpenSSL cannot generate a test key.');
        }
        openssl_pkey_export($key, $private, null, $options);
        @unlink($cnf);
        config([
            'passport.private_key' => $private,
            'passport.public_key' => openssl_pkey_get_details($key)['key'],
        ]);
    }

    public function test_upsert_creates_hidden_offer_product_once_and_updates_by_sku(): void
    {
        Passport::actingAs($this->owner, [], 'api');
        $sku = 'ACCOUNTS-GAME-990001-PS5-PRIMARY';

        $first = $this->postJson('/api/accounts/catalog/upsert/1', [
            'kind' => 'game',
            'code' => 'AVT',
            'items' => [['sku' => $sku, 'name' => 'Avatar Test Primary PS5', 'price' => 800, 'active' => true]],
        ])->assertOk()->assertJsonPath('success', true);

        $productId = $first->json('items.0.product_id');
        $variationId = $first->json('items.0.variation_id');

        $product = Product::findOrFail($productId);
        $this->assertSame('Avatar Test Primary PS5', $product->name);
        $this->assertSame(0, (int) $product->enable_stock);
        $this->assertSame(1, (int) $product->not_for_selling);
        $this->assertSame(0, (int) $product->is_inactive);
        $this->assertSame(
            AccountsCatalogService::categorySlug('game'),
            Category::find($product->category_id)?->slug
        );
        $this->assertEqualsWithDelta(800, (float) Variation::find($variationId)->sell_price_inc_tax, 0.001);

        $second = $this->postJson('/api/accounts/catalog/upsert/1', [
            'kind' => 'game',
            'items' => [['sku' => $sku, 'name' => 'Avatar Test Primary PS5', 'price' => 750, 'active' => false]],
        ])->assertOk();

        $this->assertSame($productId, $second->json('items.0.product_id'));
        $this->assertSame($variationId, $second->json('items.0.variation_id'));
        $this->assertSame(1, Product::where('business_id', 1)->where('sku', $sku)->count());
        $this->assertSame(1, (int) Product::find($productId)->is_inactive);
        $this->assertEqualsWithDelta(750, (float) Variation::find($variationId)->sell_price_inc_tax, 0.001);
    }

    public function test_products_link_to_configured_category_and_brand_slugs(): void
    {
        $category = Category::create([
            'name' => 'Cfg Digital', 'business_id' => 1, 'parent_id' => 0, 'category_type' => 'product',
            'created_by' => $this->owner->id, 'slug' => 'cfg-digital-'.uniqid(),
        ]);
        $brand = Brands::create([
            'name' => 'Cfg Brand', 'business_id' => 1, 'created_by' => $this->owner->id, 'slug' => 'cfg-brand-'.uniqid(),
        ]);
        config([
            'services.accounts.catalog_category_slug' => $category->slug,
            'services.accounts.catalog_card_category_slug' => null,
            'services.accounts.catalog_brand_slug' => $brand->slug,
        ]);

        $service = app(AccountsCatalogService::class);
        $game = $service->upsert(1, 'game', [['sku' => 'ACCOUNTS-GAME-990010-PS5-FULL', 'name' => 'Cfg Full PS5', 'price' => 10]])[0];
        $card = $service->upsert(1, 'card', [['sku' => 'ACCOUNTS-CARD-990011', 'name' => 'Cfg Card', 'price' => 10]])[0];

        foreach ([$game, $card] as $row) {
            $product = Product::find($row['product_id']);
            $this->assertSame($category->id, (int) $product->category_id);
            $this->assertSame($brand->id, (int) $product->brand_id);
        }

        // Unknown brand slug keeps the existing brand instead of clearing it.
        config(['services.accounts.catalog_brand_slug' => 'missing-brand-'.uniqid()]);
        $service->upsert(1, 'game', [['sku' => 'ACCOUNTS-GAME-990010-PS5-FULL', 'name' => 'Cfg Full PS5', 'price' => 12]]);
        $this->assertSame($brand->id, (int) Product::find($game['product_id'])->brand_id);
    }

    public function test_repeated_upserts_for_same_sku_leave_one_row(): void
    {
        $service = app(AccountsCatalogService::class);
        $sku = 'ACCOUNTS-CARD-990002';
        foreach (range(1, 3) as $_) {
            $service->upsert(1, 'card', [['sku' => $sku, 'name' => 'PSN Test 50', 'price' => 2500]]);
        }

        $this->assertSame(1, Product::where('business_id', 1)->where('sku', $sku)->count());
    }

    public function test_name_clash_appends_game_code(): void
    {
        $service = app(AccountsCatalogService::class);
        $service->upsert(1, 'game', [['sku' => 'ACCOUNTS-GAME-990003-PS4-OFFLINE', 'name' => 'Clash Offline PS4', 'price' => 100]]);
        $result = $service->upsert(1, 'game', [
            ['sku' => 'ACCOUNTS-GAME-990004-PS4-OFFLINE', 'name' => 'Clash Offline PS4', 'price' => 100],
        ], 'CLX');

        $this->assertSame('Clash Offline PS4 (CLX)', Product::find($result[0]['product_id'])->name);
    }

    public function test_only_existing_updates_linked_products_and_never_creates(): void
    {
        $service = app(AccountsCatalogService::class);
        $linked = $service->upsert(1, 'game', [
            ['sku' => 'ACCOUNTS-GAME-990006-PS5-PRIMARY', 'name' => 'Deleted Primary PS5', 'price' => 500],
        ])[0];

        $result = $service->upsert(1, 'game', [
            ['sku' => 'ACCOUNTS-GAME-990006-PS5-PRIMARY', 'name' => 'Deleted Primary PS5', 'price' => 500, 'active' => false],
            ['sku' => 'ACCOUNTS-GAME-990006-PS5-SECONDARY', 'name' => 'Deleted Secondary PS5', 'price' => 400, 'active' => false],
        ], null, true);

        $this->assertCount(1, $result);
        $this->assertSame($linked['product_id'], $result[0]['product_id']);
        $this->assertSame(1, (int) Product::find($linked['product_id'])->is_inactive);
        $this->assertSame(0, Product::where('business_id', 1)->where('sku', 'ACCOUNTS-GAME-990006-PS5-SECONDARY')->count());
    }

    public function test_rejects_non_accounts_sku_and_other_business(): void
    {
        Passport::actingAs($this->owner, [], 'api');

        $this->postJson('/api/accounts/catalog/upsert/1', [
            'kind' => 'game',
            'items' => [['sku' => '0139', 'name' => 'Bad', 'price' => 1]],
        ])->assertStatus(422);

        $this->postJson('/api/accounts/catalog/upsert/999999', [
            'kind' => 'game',
            'items' => [['sku' => 'ACCOUNTS-GAME-1-PS5-PRIMARY', 'name' => 'X', 'price' => 1]],
        ])->assertStatus(403);
    }

    public function test_order_uses_offer_product_id_as_sell_line(): void
    {
        $service = app(AccountsCatalogService::class);
        $offer = $service->upsert(1, 'game', [
            ['sku' => 'ACCOUNTS-GAME-990005-PS5-PRIMARY', 'name' => 'Sale Test Primary PS5', 'price' => 900],
        ])[0];

        $location = \App\BusinessLocation::where('business_id', 1)->orderBy('id')->firstOrFail();
        Passport::actingAs($this->owner, [], 'api');

        $response = $this->postJson('/api/accounts/orders/create/1', [
            'id' => 990005,
            'location_id' => $location->id,
            'total' => '900',
            'discount_total' => '0',
            'shipping_total' => '0',
            'date_created' => now()->toIso8601String(),
            'date_paid' => null,
            'payment_method_title' => 'Cash on delivery',
            'billing' => ['first_name' => 'Offer', 'last_name' => 'Test', 'phone' => '01099000005'],
            'shipping' => [],
            'line_items' => [[
                'id' => 1,
                'name' => 'ps5_primary_stock',
                'quantity' => 1,
                'total' => '900',
                'sku' => 'ACCOUNTS-GAME-990005-PS5-PRIMARY',
                'meta_data' => [
                    ['key' => 'game_title', 'value' => 'Sale Test'],
                    ['key' => 'type', 'value' => 'primary'],
                    ['key' => '_account', 'value' => 'sale@example.com'],
                    ['key' => '_password', 'value' => 'pw-1'],
                    ['key' => '_pos_product_id', 'value' => $offer['product_id']],
                ],
            ]],
        ])->assertOk();

        $transactionId = $response->json('created.id');
        $line = \App\TransactionSellLine::where('transaction_id', $transactionId)->firstOrFail();
        $this->assertSame($offer['product_id'], (int) $line->product_id);
        $this->assertSame($offer['variation_id'], (int) $line->variation_id);
    }
}
