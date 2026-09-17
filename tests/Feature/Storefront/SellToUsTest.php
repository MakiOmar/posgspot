<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Mail\StorefrontSellRequestSubmitted;
use App\StorefrontSellRequest;
use App\Transaction;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SellToUsTest extends TestCase
{
    protected int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'storefront.sell_to_us.enabled' => true,
            'storefront.sell_to_us.max_photos' => 6,
            'storefront.sell_to_us.max_photo_kb' => 4096,
        ]);
    }

    private function registerAndLogin(): array
    {
        $email = 'sell_test_'.uniqid().'@example.com';
        $mobile = '+2010'.random_int(10000000, 99999999);

        $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Sell',
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

    private function createFinalOrder(int $contactId, string $invoiceNo): Transaction
    {
        $locationId = BusinessLocation::where('business_id', $this->businessId)
            ->where('is_active', 1)
            ->value('id');

        return Transaction::create([
            'business_id' => $this->businessId,
            'location_id' => $locationId,
            'type' => 'sell',
            'status' => 'final',
            'contact_id' => $contactId,
            'invoice_no' => $invoiceNo,
            'storefront_order_id' => 'web-sell-'.uniqid(),
            'transaction_date' => now(),
            'total_before_tax' => 250,
            'final_total' => 250,
            'created_by' => 1,
            'source' => 'storefront',
        ]);
    }

    public function test_sell_to_us_unavailable_when_disabled(): void
    {
        config(['storefront.sell_to_us.enabled' => false]);

        $this->getJson('/api/storefront/v1/sell-to-us/meta')
            ->assertStatus(404);

        $auth = $this->registerAndLogin();
        $this->withHeader('Authorization', 'Bearer '.$auth['token'])
            ->postJson('/api/storefront/v1/sell-to-us/verify-invoice', ['invoice_no' => 'X'])
            ->assertStatus(404);
    }

    public function test_settings_exposes_sell_to_us_flag(): void
    {
        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.sell_to_us.enabled', true);

        config(['storefront.sell_to_us.enabled' => false]);

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.sell_to_us.enabled', false);
    }

    public function test_meta_returns_types_and_options(): void
    {
        $this->getJson('/api/storefront/v1/sell-to-us/meta')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.types.0.id', 'account')
            ->assertJsonPath('data.types.1.id', 'disc')
            ->assertJsonPath('data.types.2.id', 'device')
            ->assertJsonStructure([
                'data' => [
                    'cities',
                    'platforms',
                    'device_models',
                    'storage_options',
                    'conditions',
                    'purchased_from_us',
                    'max_photos',
                ],
            ]);
    }

    public function test_verify_and_submit_require_auth(): void
    {
        $this->postJson('/api/storefront/v1/sell-to-us/verify-invoice', ['invoice_no' => 'X'])
            ->assertStatus(401);

        $this->postJson('/api/storefront/v1/sell-to-us/requests', [
            'type' => 'account',
            'name' => 'Guest',
        ])->assertStatus(401);
    }

    public function test_verify_rejects_other_customers_invoice(): void
    {
        $owner = $this->registerAndLogin();
        $invoice = 'INV-SELL-'.uniqid();
        $this->createFinalOrder($owner['contact_id'], $invoice);

        $other = $this->registerAndLogin();

        $this->withHeader('Authorization', 'Bearer '.$other['token'])
            ->postJson('/api/storefront/v1/sell-to-us/verify-invoice', [
                'invoice_no' => $invoice,
            ])
            ->assertStatus(422);
    }

    public function test_verify_accepts_own_invoice(): void
    {
        $auth = $this->registerAndLogin();
        $invoice = 'INV-OWN-'.uniqid();
        $order = $this->createFinalOrder($auth['contact_id'], $invoice);

        $this->withHeader('Authorization', 'Bearer '.$auth['token'])
            ->postJson('/api/storefront/v1/sell-to-us/verify-invoice', [
                'invoice_no' => $invoice,
            ])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.order.id', $order->id)
            ->assertJsonPath('data.order.invoice_no', $invoice);
    }

    public function test_submit_without_purchased_from_us_does_not_require_invoice(): void
    {
        Mail::fake();
        $auth = $this->registerAndLogin();

        $this->withHeader('Authorization', 'Bearer '.$auth['token'])
            ->postJson('/api/storefront/v1/sell-to-us/requests', [
                'type' => 'account',
                'name' => 'Sell Tester',
                'email' => 'sell@example.com',
                'phone' => '+201012345678',
                'city' => 'Cairo',
                'purchased_from_us' => false,
                'details' => ['account_note' => 'Primary PSN'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'account')
            ->assertJsonPath('data.status', 'new');

        $this->assertDatabaseHas('storefront_sell_requests', [
            'contact_id' => $auth['contact_id'],
            'type' => 'account',
            'purchased_from_us' => 0,
        ]);

        Mail::assertQueued(StorefrontSellRequestSubmitted::class);
    }

    public function test_submit_with_purchased_from_us_requires_valid_invoice(): void
    {
        $auth = $this->registerAndLogin();

        $this->withHeader('Authorization', 'Bearer '.$auth['token'])
            ->postJson('/api/storefront/v1/sell-to-us/requests', [
                'type' => 'disc',
                'name' => 'Sell Tester',
                'purchased_from_us' => true,
                'invoice_no' => 'DOES-NOT-EXIST',
                'details' => [
                    'game_title' => 'Some Game',
                    'platform' => 'ps5',
                    'condition' => 'good',
                ],
            ])
            ->assertStatus(422);
    }

    public function test_happy_path_with_verified_invoice(): void
    {
        Mail::fake();
        $auth = $this->registerAndLogin();
        $invoice = 'INV-HAPPY-'.uniqid();
        $order = $this->createFinalOrder($auth['contact_id'], $invoice);

        $this->withHeader('Authorization', 'Bearer '.$auth['token'])
            ->postJson('/api/storefront/v1/sell-to-us/requests', [
                'type' => 'device',
                'name' => 'Sell Tester',
                'email' => 'sell@example.com',
                'city' => 'Giza',
                'purchased_from_us' => true,
                'invoice_no' => $invoice,
                'transaction_id' => $order->id,
                'details' => [
                    'model' => 'ps5_slim',
                    'storage' => '1tb',
                    'condition' => 'excellent',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'device')
            ->assertJsonPath('data.transaction_id', $order->id);

        $this->assertTrue(
            StorefrontSellRequest::where('contact_id', $auth['contact_id'])
                ->where('transaction_id', $order->id)
                ->where('type', 'device')
                ->exists()
        );

        Mail::assertQueued(StorefrontSellRequestSubmitted::class);
    }
}
