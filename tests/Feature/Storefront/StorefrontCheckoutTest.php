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

    private function firstShippingRateId(int $variationId, int $locationId): string
    {
        app(\App\Services\Storefront\Shipping\ShippingLegacyMigrator::class)
            ->ensureDefaultZones($this->businessId);

        $quoted = app(\App\Services\Storefront\Shipping\ShippingQuoteService::class)->quote(
            $this->businessId,
            100,
            [['variation_id' => $variationId, 'quantity' => 1]],
            ['country' => 'EG', 'state' => 'C'],
            null,
            $locationId,
            'en',
            false
        );

        $rate = $quoted['available_rates'][0] ?? null;
        if (empty($rate['id'])) {
            $this->markTestSkipped('No shipping rates available for test.');
        }

        return $rate['id'];
    }

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
        $shippingRateId = $this->firstShippingRateId($variation->id, $location->id);

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
                'state' => 'C',
                'country' => 'EG',
            ],
            'shipping_rate_id' => $shippingRateId,
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
                'state' => 'C',
                'country' => 'EG',
            ],
            'shipping_rate_id' => $this->firstShippingRateId($variation->id, $location->id),
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

    public function test_digital_checkout_uses_catalog_price_not_pos_sku_price(): void
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

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
            'default_fulfillment_location_id' => $location->id,
            'cod_enabled' => true,
        ]);
        Cache::flush();

        $originalDefault = $variation->default_sell_price;
        $originalInc = $variation->sell_price_inc_tax;
        $variation->default_sell_price = 0;
        $variation->sell_price_inc_tax = 0;
        $variation->save();

        try {
            $quotes = app(\App\Services\Storefront\Shipping\ShippingQuoteService::class);
            $digitalItems = [[
                'variation_id' => $variation->id,
                'quantity' => 1,
                'digital' => [
                    'kind' => 'game',
                    'game_id' => 99,
                    'type' => 'secondary',
                    'platform' => '5',
                    'line_key' => 'ps5_secondary_stock|game:99',
                    'title' => 'Catalog Game Title',
                    'price' => 275,
                ],
            ]];
            $quoted = $quotes->quote($this->businessId, 275, $digitalItems, [], null, $location->id, 'en', false);
            $rateId = $quoted['available_rates'][0]['id'] ?? null;
            if (empty($rateId)) {
                $this->markTestSkipped('No digital shipping rate.');
            }

            $orderKey = 'SF-DIG-PRICE-'.uniqid();
            $response = $this->postJson('/api/storefront/v1/checkout', [
                'idempotency_key' => $orderKey,
                'location_id' => $location->id,
                'payment_method' => 'cod',
                'items' => $digitalItems,
                'customer' => [
                    'first_name' => 'Digital',
                    'last_name' => 'Price',
                    'email' => 'digital_price_'.uniqid().'@example.com',
                    'mobile' => '201011122233',
                ],
                'shipping_address' => [
                    'country' => 'EG',
                    'address_line_1' => 'Digital delivery',
                ],
                'shipping_rate_id' => $rateId,
            ]);

            $response->assertCreated()->assertJsonPath('success', true);

            $transaction = Transaction::where('storefront_order_id', $orderKey)->first();
            $this->assertNotNull($transaction);
            $this->assertEqualsWithDelta(275.0, (float) $transaction->final_total, 0.0001);
            $this->assertEqualsWithDelta(275.0, (float) $transaction->total_before_tax, 0.0001);

            $line = $transaction->sell_lines()->first();
            $this->assertNotNull($line);
            $this->assertEqualsWithDelta(275.0, (float) $line->unit_price_inc_tax, 0.0001);
            $this->assertSame('Catalog Game Title', (string) $line->sell_line_note);
        } finally {
            $variation->default_sell_price = $originalDefault;
            $variation->sell_price_inc_tax = $originalInc;
            $variation->save();
        }
    }
}
