<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Product;
use App\Services\Storefront\Payment\GeideaSignature;
use App\Services\Storefront\StorefrontSettingService;
use App\Transaction;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GeideaPaymentTest extends TestCase
{
    protected int $businessId = 1;

    public function test_geidea_checkout_returns_session_id_without_secrets(): void
    {
        Mail::fake();
        [$location, $variation] = $this->resolveCheckoutFixtures();
        if ($location === null) {
            return;
        }

        $this->saveGeideaSettings($location->id);
        Http::fake([
            'https://api.merchant.geidea.net/payment-intent/api/v2/direct/session' => Http::response([
                'session' => ['id' => 'sess-abc'],
            ], 200),
        ]);

        $orderKey = 'SF-GEIDEA-'.uniqid();
        $response = $this->postCheckout($orderKey, $location->id, $variation->id);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment.provider', 'geidea')
            ->assertJsonPath('data.payment.session_id', 'sess-abc')
            ->assertJsonPath('data.payment.environment', 'test')
            ->assertJsonPath('data.payment.region', 'egypt');

        $payment = $response->json('data.payment');
        $this->assertIsArray($payment);
        $this->assertArrayNotHasKey('apiPassword', $payment);
        $this->assertArrayNotHasKey('merchantPublicKey', $payment);
        $this->assertArrayNotHasKey('signature', $payment);

        $transaction = Transaction::where('storefront_order_id', $orderKey)->first();
        $this->assertNotNull($transaction);
        $this->assertSame('test', $transaction->storefront_payment_meta['mode'] ?? null);
    }

    public function test_geidea_webhook_marks_order_paid_with_valid_signature(): void
    {
        Mail::fake();
        [$location, $variation] = $this->resolveCheckoutFixtures();
        if ($location === null) {
            return;
        }

        $this->saveGeideaSettings($location->id);
        Http::fake($this->geideaHttpFake('sess-pay'));

        $orderKey = 'SF-GEI-WH-'.uniqid();
        $this->postCheckout($orderKey, $location->id, $variation->id)->assertCreated();

        $transaction = Transaction::where('storefront_order_id', $orderKey)->firstOrFail();
        $amount = number_format((float) $transaction->final_total, 2, '.', '');
        $payload = $this->signedCallback($orderKey, $amount, 'test-pw', 'Success', 'Paid');

        Http::fake($this->geideaHttpFake('sess-pay', [
            'orderId' => 'gid-paid',
            'status' => 'Success',
            'detailedStatus' => 'Paid',
            'amount' => (float) $amount,
            'currency' => 'EGP',
            'merchantReferenceId' => $orderKey,
        ]));

        $this->postJson('/api/storefront/v1/payments/geidea/webhook', $payload)
            ->assertOk();

        $transaction->refresh();
        $this->assertSame('paid', strtolower((string) $transaction->payment_status));
        $this->assertSame('gid-paid', $transaction->storefront_payment_meta['geidea_order_id'] ?? null);
    }

    public function test_geidea_webhook_rejects_tampered_signature(): void
    {
        Mail::fake();
        [$location, $variation] = $this->resolveCheckoutFixtures();
        if ($location === null) {
            return;
        }

        $this->saveGeideaSettings($location->id);
        Http::fake($this->geideaHttpFake());

        $orderKey = 'SF-GEI-TAMPER-'.uniqid();
        $this->postCheckout($orderKey, $location->id, $variation->id)->assertCreated();
        $transaction = Transaction::where('storefront_order_id', $orderKey)->firstOrFail();
        $amount = number_format((float) $transaction->final_total, 2, '.', '');
        $payload = $this->signedCallback($orderKey, $amount, 'test-pw');
        $payload['signature'] = 'not-a-valid-signature';

        $this->postJson('/api/storefront/v1/payments/geidea/webhook', $payload)
            ->assertStatus(400);

        $transaction->refresh();
        $this->assertNotSame('paid', strtolower((string) $transaction->payment_status));
    }

    public function test_geidea_webhook_rejects_amount_mismatch(): void
    {
        Mail::fake();
        [$location, $variation] = $this->resolveCheckoutFixtures();
        if ($location === null) {
            return;
        }

        $this->saveGeideaSettings($location->id);
        Http::fake($this->geideaHttpFake());

        $orderKey = 'SF-GEI-AMT-'.uniqid();
        $this->postCheckout($orderKey, $location->id, $variation->id)->assertCreated();
        $payload = $this->signedCallback($orderKey, '1.00', 'test-pw');

        $this->postJson('/api/storefront/v1/payments/geidea/webhook', $payload)
            ->assertStatus(400);
    }

    public function test_geidea_webhook_replay_does_not_double_fulfil(): void
    {
        Mail::fake();
        [$location, $variation] = $this->resolveCheckoutFixtures();
        if ($location === null) {
            return;
        }

        $this->saveGeideaSettings($location->id);
        $orderKey = 'SF-GEI-REPLAY-'.uniqid();
        Http::fake($this->geideaHttpFake());
        $this->postCheckout($orderKey, $location->id, $variation->id)->assertCreated();

        $transaction = Transaction::where('storefront_order_id', $orderKey)->firstOrFail();
        $amount = number_format((float) $transaction->final_total, 2, '.', '');
        $payload = $this->signedCallback($orderKey, $amount, 'test-pw');

        Http::fake($this->geideaHttpFake('sess-pay', [
            'orderId' => 'gid-paid',
            'status' => 'Success',
            'detailedStatus' => 'Paid',
            'amount' => (float) $amount,
            'currency' => 'EGP',
            'merchantReferenceId' => $orderKey,
        ]));

        $this->postJson('/api/storefront/v1/payments/geidea/webhook', $payload)->assertOk();
        $this->postJson('/api/storefront/v1/payments/geidea/webhook', $payload)->assertOk();

        $transaction->refresh();
        $this->assertSame('paid', strtolower((string) $transaction->payment_status));
        $completed = $transaction->payment_lines()->where('payment_line_status', 'completed')->count();
        $this->assertGreaterThanOrEqual(1, $completed);
    }

    public function test_geidea_webhook_cancel_does_not_mark_paid(): void
    {
        Mail::fake();
        [$location, $variation] = $this->resolveCheckoutFixtures();
        if ($location === null) {
            return;
        }

        $this->saveGeideaSettings($location->id);
        Http::fake($this->geideaHttpFake());
        $orderKey = 'SF-GEI-FAIL-'.uniqid();
        $this->postCheckout($orderKey, $location->id, $variation->id)->assertCreated();
        $transaction = Transaction::where('storefront_order_id', $orderKey)->firstOrFail();
        $amount = number_format((float) $transaction->final_total, 2, '.', '');
        $payload = $this->signedCallback($orderKey, $amount, 'test-pw', 'Failed', 'Cancelled');

        $this->postJson('/api/storefront/v1/payments/geidea/webhook', $payload)
            ->assertOk();

        $transaction->refresh();
        $this->assertNotSame('paid', strtolower((string) $transaction->payment_status));
    }

    public function test_geidea_unhashed_detailed_status_is_confirmed_remotely(): void
    {
        Mail::fake();
        [$location, $variation] = $this->resolveCheckoutFixtures();
        if ($location === null) {
            return;
        }

        $this->saveGeideaSettings($location->id);
        Http::fake($this->geideaHttpFake());
        $orderKey = 'SF-GEI-DS-'.uniqid();
        $this->postCheckout($orderKey, $location->id, $variation->id)->assertCreated();
        $transaction = Transaction::where('storefront_order_id', $orderKey)->firstOrFail();
        $amount = number_format((float) $transaction->final_total, 2, '.', '');
        $payload = $this->signedCallback($orderKey, $amount, 'test-pw', 'Success', 'Paid');

        Http::fake($this->geideaHttpFake('sess-pay', [
            'orderId' => 'gid-auth',
            'status' => 'Success',
            'detailedStatus' => 'Authorized',
            'amount' => (float) $amount,
            'currency' => 'EGP',
            'merchantReferenceId' => $orderKey,
        ]));

        $this->postJson('/api/storefront/v1/payments/geidea/webhook', $payload)
            ->assertStatus(400);

        $transaction->refresh();
        $this->assertNotSame('paid', strtolower((string) $transaction->payment_status));
    }

    public function test_geidea_test_signature_is_rejected_for_live_mode_transaction(): void
    {
        Mail::fake();
        [$location, $variation] = $this->resolveCheckoutFixtures();
        if ($location === null) {
            return;
        }

        $this->saveGeideaSettings($location->id);
        Http::fake($this->geideaHttpFake());
        $orderKey = 'SF-GEI-MODE-'.uniqid();
        $this->postCheckout($orderKey, $location->id, $variation->id)->assertCreated();

        $transaction = Transaction::where('storefront_order_id', $orderKey)->firstOrFail();
        $meta = $transaction->storefront_payment_meta ?? [];
        $meta['mode'] = 'live';
        $transaction->storefront_payment_meta = $meta;
        $transaction->save();

        $amount = number_format((float) $transaction->final_total, 2, '.', '');
        $payload = $this->signedCallback($orderKey, $amount, 'test-pw');

        $this->postJson('/api/storefront/v1/payments/geidea/webhook', $payload)
            ->assertStatus(400);

        $transaction->refresh();
        $this->assertNotSame('paid', strtolower((string) $transaction->payment_status));
    }

    public function test_settings_expose_geidea_online_payments_flags(): void
    {
        $this->saveGeideaSettings();

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.online_payments.enabled', true)
            ->assertJsonPath('data.online_payments.provider', 'geidea')
            ->assertJsonPath('data.online_payments.label', 'Geidea')
            ->assertJsonPath('data.online_payments.region', 'EGY-PROD')
            ->assertJsonPath('data.online_payments.environment', 'test');
    }

    private function saveGeideaSettings(?int $locationId = null): void
    {
        $payload = [
            'gateway' => [
                'provider' => 'geidea',
                'enabled' => true,
                'geidea' => [
                    'mode' => 'test',
                    'region' => 'EGY-PROD',
                    'currency' => 'EGP',
                    'test_public_key' => 'test-pk',
                    'test_api_password' => 'test-pw',
                    'live_public_key' => 'live-pk',
                    'live_api_password' => 'live-pw',
                ],
            ],
        ];
        if ($locationId) {
            $payload['selling_location_ids'] = [$locationId];
            $payload['default_fulfillment_location_id'] = $locationId;
            $payload['cod_enabled'] = true;
        }

        app(StorefrontSettingService::class)->save($this->businessId, $payload);
        Cache::flush();
    }

    /**
     * @param  array<string, mixed>|null  $order
     * @return array<string, \GuzzleHttp\Promise\PromiseInterface>
     */
    private function geideaHttpFake(string $sessionId = 'sess-abc', ?array $order = null): array
    {
        $orderPayload = $order ?? [
            'orderId' => 'gid-paid',
            'status' => 'Success',
            'detailedStatus' => 'Paid',
            'amount' => 1,
            'currency' => 'EGP',
            'merchantReferenceId' => 'x',
        ];

        return [
            'https://api.merchant.geidea.net/payment-intent/api/v2/direct/session' => Http::response([
                'session' => ['id' => $sessionId],
            ], 200),
            'https://api.merchant.geidea.net/pgw/api/v1/direct/order/*' => Http::response([
                'order' => $orderPayload,
            ], 200),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function signedCallback(
        string $orderKey,
        string $amount,
        string $password,
        string $status = 'Success',
        string $detailedStatus = 'Paid',
    ): array {
        $order = [
            'amount' => (float) $amount,
            'currency' => 'EGP',
            'orderId' => 'gid-paid',
            'status' => $status,
            'detailedStatus' => $detailedStatus,
            'merchantReferenceId' => $orderKey,
        ];
        $timeStamp = '9/14/2026 3:17:05 PM';
        $signature = app(GeideaSignature::class)->callback(
            'test-pk',
            $amount,
            'EGP',
            'gid-paid',
            $status,
            $orderKey,
            $timeStamp,
            $password
        );

        return [
            'order' => $order,
            'timeStamp' => $timeStamp,
            'signature' => $signature,
        ];
    }

    private function postCheckout(string $orderKey, int $locationId, int $variationId)
    {
        return $this->postJson('/api/storefront/v1/checkout', [
            'idempotency_key' => $orderKey,
            'location_id' => $locationId,
            'payment_method' => 'geidea',
            'items' => [
                ['variation_id' => $variationId, 'quantity' => 1],
            ],
            'customer' => [
                'first_name' => 'Geidea',
                'last_name' => 'Test',
                'email' => 'geidea-test@example.com',
                'mobile' => '+201000000009',
            ],
            'shipping_address' => [
                'address_line_1' => 'Test address',
                'city' => 'Cairo',
                'state' => 'C',
                'country' => 'EG',
            ],
            'shipping_rate_id' => $this->firstShippingRateId($variationId, $locationId),
        ], [
            'X-Content-Locale' => 'en',
        ]);
    }

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
