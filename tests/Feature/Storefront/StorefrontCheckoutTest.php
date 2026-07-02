<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Product;
use App\Services\Storefront\StorefrontSettingService;
use App\StorefrontSetting;
use App\Transaction;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Storefront checkout creates POS sell transactions.
 */
class StorefrontCheckoutTest extends TestCase
{
    protected int $businessId = 1;

    public function test_cod_checkout_creates_storefront_transaction(): void
    {
        Mail::fake();

        $location = BusinessLocation::where('business_id', $this->businessId)
            ->where('is_active', 1)
            ->first();

        if (empty($location)) {
            $this->markTestSkipped('No active business location in database.');
        }

        $product = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->where('enable_stock', 1)
            ->first();

        if (empty($product)) {
            $this->markTestSkipped('No stocked sellable product in database.');
        }

        $variation = Variation::where('product_id', $product->id)->whereNull('deleted_at')->first();
        if (empty($variation)) {
            $this->markTestSkipped('No variation for product.');
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
            'default_fulfillment_location_id' => $location->id,
            'cod_enabled' => true,
        ]);
        Cache::flush();

        VariationLocationDetails::updateOrCreate(
            [
                'variation_id' => $variation->id,
                'location_id' => $location->id,
            ],
            [
                'product_id' => $product->id,
                'product_variation_id' => $variation->product_variation_id,
                'qty_available' => 25,
            ]
        );

        $orderKey = 'SF-TEST-'.uniqid();

        $response = $this->postJson('/api/storefront/v1/checkout', [
            'idempotency_key' => $orderKey,
            'location_id' => $location->id,
            'payment_method' => 'cod',
            'items' => [
                ['variation_id' => $variation->id, 'quantity' => 1],
            ],
            'customer' => [
                'first_name' => 'Checkout',
                'last_name' => 'Test',
                'email' => 'checkout_test_'.uniqid().'@example.com',
                'mobile' => '201012345678',
            ],
            'shipping_address' => [
                'address_line_1' => '12 Test Street',
                'city' => 'Cairo',
                'country' => 'Egypt',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.storefront_order_id', $orderKey);

        $transaction = Transaction::where('storefront_order_id', $orderKey)->first();
        $this->assertNotNull($transaction);
        $this->assertSame('storefront', $transaction->source);
        $this->assertSame('final', $transaction->status);
    }

    public function test_checkout_is_idempotent_for_same_key(): void
    {
        Mail::fake();

        $location = BusinessLocation::where('business_id', $this->businessId)->where('is_active', 1)->first();
        if (empty($location)) {
            $this->markTestSkipped('No active business location in database.');
        }

        $product = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->first();

        if (empty($product)) {
            $this->markTestSkipped('No sellable product in database.');
        }

        $variation = Variation::where('product_id', $product->id)->whereNull('deleted_at')->first();
        if (empty($variation)) {
            $this->markTestSkipped('No variation for product.');
        }

        StorefrontSetting::updateOrCreate(
            ['business_id' => $this->businessId],
            ['value' => [
                'selling_location_ids' => [$location->id],
                'default_fulfillment_location_id' => $location->id,
                'cod_enabled' => true,
            ]]
        );
        Cache::flush();

        VariationLocationDetails::updateOrCreate(
            ['variation_id' => $variation->id, 'location_id' => $location->id],
            [
                'product_id' => $product->id,
                'product_variation_id' => $variation->product_variation_id,
                'qty_available' => 10,
            ]
        );

        $payload = [
            'idempotency_key' => 'SF-IDEM-'.uniqid(),
            'location_id' => $location->id,
            'payment_method' => 'cod',
            'items' => [['variation_id' => $variation->id, 'quantity' => 1]],
            'customer' => [
                'first_name' => 'Idem',
                'last_name' => 'Test',
                'email' => 'idem_'.uniqid().'@example.com',
            ],
            'shipping_address' => [
                'address_line_1' => '1 Idempotent Rd',
                'city' => 'Cairo',
                'country' => 'Egypt',
            ],
        ];

        $first = $this->postJson('/api/storefront/v1/checkout', $payload);
        $first->assertCreated();
        $firstId = $first->json('data.id');

        $second = $this->postJson('/api/storefront/v1/checkout', $payload);
        $second->assertCreated()
            ->assertJsonPath('data.id', $firstId);

        $this->assertSame(
            1,
            Transaction::where('storefront_order_id', $payload['idempotency_key'])->count()
        );
    }
}
