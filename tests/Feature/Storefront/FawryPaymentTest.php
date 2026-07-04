<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Product;
use App\Services\Storefront\StorefrontSettingService;
use App\Transaction;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FawryPaymentTest extends TestCase
{
    protected int $businessId = 1;

    public function test_fawry_checkout_returns_payment_session_when_gateway_enabled(): void
    {
        Mail::fake();

        [$location, $variation] = $this->resolveCheckoutFixtures();
        if ($location === null) {
            return;
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
            'default_fulfillment_location_id' => $location->id,
            'cod_enabled' => true,
            'gateway' => [
                'provider' => 'fawry',
                'enabled' => true,
                'fawry' => [
                    'merchant_code' => 'TEST_MERCHANT',
                    'security_key' => 'test-security-key',
                    'staging' => true,
                ],
            ],
        ]);
        Cache::flush();

        $orderKey = 'SF-FAWRY-'.uniqid();

        $response = $this->postJson('/api/storefront/v1/checkout', [
            'idempotency_key' => $orderKey,
            'location_id' => $location->id,
            'payment_method' => 'fawry',
            'items' => [
                ['variation_id' => $variation->id, 'quantity' => 1],
            ],
            'customer' => [
                'first_name' => 'Fawry',
                'last_name' => 'Test',
                'email' => 'fawry-test@example.com',
                'mobile' => '+201000000000',
            ],
            'shipping_address' => [
                'address_line_1' => 'Test address',
                'city' => 'Cairo',
                'country' => 'Egypt',
            ],
        ], [
            'X-Content-Locale' => 'en',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment.provider', 'fawry')
            ->assertJsonPath('data.payment.charge.merchantRefNum', $orderKey)
            ->assertJsonStructure([
                'data' => [
                    'payment' => [
                        'sdk_url',
                        'charge' => ['signature', 'chargeItems', 'returnUrl'],
                    ],
                ],
            ]);

        $transaction = Transaction::where('business_id', $this->businessId)
            ->where('storefront_order_id', $orderKey)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertSame('due', strtolower((string) $transaction->payment_status));
    }

    public function test_fawry_webhook_marks_order_paid_with_valid_signature(): void
    {
        [$location, $variation] = $this->resolveCheckoutFixtures();
        if ($location === null) {
            return;
        }

        $securityKey = 'webhook-secret';
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
            'default_fulfillment_location_id' => $location->id,
            'gateway' => [
                'provider' => 'fawry',
                'enabled' => true,
                'fawry' => [
                    'merchant_code' => 'TEST_MERCHANT',
                    'security_key' => $securityKey,
                    'staging' => true,
                ],
            ],
        ]);
        Cache::flush();

        $orderKey = 'SF-WH-'.uniqid();
        $this->postJson('/api/storefront/v1/checkout', [
            'idempotency_key' => $orderKey,
            'location_id' => $location->id,
            'payment_method' => 'fawry',
            'items' => [
                ['variation_id' => $variation->id, 'quantity' => 1],
            ],
            'customer' => [
                'first_name' => 'Webhook',
                'last_name' => 'Test',
                'email' => 'webhook-test@example.com',
                'mobile' => '+201000000001',
            ],
            'shipping_address' => [
                'address_line_1' => 'Test address',
                'city' => 'Cairo',
                'country' => 'Egypt',
            ],
        ])->assertCreated();

        $transaction = Transaction::where('storefront_order_id', $orderKey)->firstOrFail();
        $amount = number_format((float) $transaction->final_total, 2, '.', '');

        $payload = [
            'fawryRefNumber' => 'FAW999',
            'merchantRefNumber' => $orderKey,
            'paymentAmount' => (float) $amount,
            'orderAmount' => (float) $amount,
            'orderStatus' => 'PAID',
            'paymentMethod' => 'CARD',
            'paymentRefrenceNumber' => 'PAY999',
        ];

        $signatureString = 'FAW999'
            .$orderKey
            .$amount
            .$amount
            .'PAID'
            .'CARD'
            .'PAY999'
            .$securityKey;
        $payload['messageSignature'] = hash('sha256', $signatureString);

        $response = $this->postJson('/api/storefront/v1/payments/fawry/webhook', $payload);

        $response->assertOk()
            ->assertJsonPath('status', '200');

        $transaction->refresh();
        $this->assertSame('paid', strtolower((string) $transaction->payment_status));
    }

    public function test_settings_expose_online_payments_flags(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'gateway' => [
                'provider' => 'fawry',
                'enabled' => true,
                'fawry' => [
                    'merchant_code' => 'TEST_MERCHANT',
                    'security_key' => 'secret',
                    'staging' => true,
                ],
            ],
        ]);
        Cache::flush();

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.online_payments.enabled', true)
            ->assertJsonPath('data.online_payments.provider', 'fawry')
            ->assertJsonPath('data.online_payments.label', 'FawryPay');
    }

    /**
     * @return array{0: BusinessLocation|null, 1: Variation|null}
     */
    private function resolveCheckoutFixtures(): array
    {
        $location = BusinessLocation::where('business_id', $this->businessId)
            ->where('is_active', 1)
            ->first();

        if (empty($location)) {
            $this->markTestSkipped('No active business location in database.');

            return [null, null];
        }

        $product = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->where('enable_stock', 1)
            ->first();

        if (empty($product)) {
            $this->markTestSkipped('No stocked sellable product in database.');

            return [null, null];
        }

        $variation = Variation::where('product_id', $product->id)->whereNull('deleted_at')->first();
        if (empty($variation)) {
            $this->markTestSkipped('No variation for product.');

            return [null, null];
        }

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

        return [$location, $variation];
    }
}
