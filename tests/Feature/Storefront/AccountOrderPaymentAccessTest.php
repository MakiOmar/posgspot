<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Services\Storefront\StorefrontSettingService;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Signed-in customers can pay unpaid storefront orders ("Pay now") via POST /account/orders/{id}/payment-access.
 */
class AccountOrderPaymentAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected int $businessId = 1;

    public function test_unpaid_order_is_payable_and_access_token_opens_a_payment_session(): void
    {
        $this->enableGeidea(true);
        $session = $this->registerAndLogin();
        $order = $this->createOrder($session['contact_id'], 'due');

        $this->withToken($session['token'])
            ->getJson('/api/storefront/v1/account/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.can_pay_online', true)
            ->assertJsonMissingPath('data.fulfillment_location');

        $access = $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/account/orders/'.$order->id.'/payment-access')
            ->assertOk()
            ->assertJsonPath('data.storefront_order_id', $order->storefront_order_id)
            ->json('data.order_access_token');

        $this->assertIsString($access);
        $this->assertNotSame('', $access);

        Http::fake([
            'https://api.merchant.geidea.net/payment-intent/api/v2/direct/session' => Http::response([
                'session' => ['id' => 'sess-pay-now'],
            ], 200),
        ]);

        $this->postJson('/api/storefront/v1/payments/geidea/session', [
            'storefront_order_id' => $order->storefront_order_id,
            'order_access_token' => $access,
            'locale' => 'en',
        ])
            ->assertOk()
            ->assertJsonPath('data.provider', 'geidea')
            ->assertJsonPath('data.session_id', 'sess-pay-now');
    }

    public function test_paid_order_is_not_payable(): void
    {
        $this->enableGeidea(true);
        $session = $this->registerAndLogin();
        $order = $this->createOrder($session['contact_id'], 'paid');

        $this->withToken($session['token'])
            ->getJson('/api/storefront/v1/account/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.can_pay_online', false);

        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/account/orders/'.$order->id.'/payment-access')
            ->assertStatus(422);
    }

    public function test_unpaid_order_is_not_payable_when_online_payments_are_disabled(): void
    {
        $this->enableGeidea(false);
        $session = $this->registerAndLogin();
        $order = $this->createOrder($session['contact_id'], 'due');

        $this->withToken($session['token'])
            ->getJson('/api/storefront/v1/account/orders')
            ->assertOk()
            ->assertJsonPath('data.0.can_pay_online', false);

        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/account/orders/'.$order->id.'/payment-access')
            ->assertStatus(422);
    }

    public function test_in_store_sale_without_storefront_reference_is_not_payable(): void
    {
        $this->enableGeidea(true);
        $session = $this->registerAndLogin();
        $order = $this->createOrder($session['contact_id'], 'due', null);

        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/account/orders/'.$order->id.'/payment-access')
            ->assertStatus(422);
    }

    public function test_cannot_get_payment_access_for_another_customers_order(): void
    {
        $this->enableGeidea(true);
        $owner = $this->registerAndLogin();
        $order = $this->createOrder($owner['contact_id'], 'due');
        $other = $this->registerAndLogin();

        $this->withToken($other['token'])
            ->postJson('/api/storefront/v1/account/orders/'.$order->id.'/payment-access')
            ->assertNotFound();
    }

    public function test_payment_access_requires_authentication(): void
    {
        $this->postJson('/api/storefront/v1/account/orders/1/payment-access')
            ->assertUnauthorized();
    }

    private function enableGeidea(bool $enabled): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'gateway' => [
                'provider' => 'geidea',
                'enabled' => $enabled,
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
        ]);
        Cache::flush();
    }

    /**
     * @return array{token: string, contact_id: int}
     */
    private function registerAndLogin(): array
    {
        Mail::fake();

        $register = $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Payer',
            'email' => 'pay_now_'.uniqid().'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        return [
            'token' => (string) $register->json('data.token'),
            'contact_id' => (int) $register->json('data.contact.id'),
        ];
    }

    private function createOrder(int $contactId, string $paymentStatus, ?string $storefrontOrderId = 'auto'): Transaction
    {
        $locationId = BusinessLocation::where('business_id', $this->businessId)
            ->where('is_active', 1)
            ->value('id');

        if (empty($locationId)) {
            $this->markTestSkipped('No active business location in database.');
        }

        return Transaction::create([
            'business_id' => $this->businessId,
            'location_id' => $locationId,
            'type' => 'sell',
            'status' => 'final',
            'contact_id' => $contactId,
            'transaction_date' => now(),
            'total_before_tax' => 120,
            'final_total' => 120,
            'payment_status' => $paymentStatus,
            'invoice_no' => 'PAYNOW-'.uniqid(),
            'storefront_order_id' => $storefrontOrderId === 'auto' ? 'SF-PAYNOW-'.uniqid() : $storefrontOrderId,
            'created_by' => 1,
            'source' => 'storefront',
        ]);
    }
}
