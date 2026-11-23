<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Contact;
use App\Services\Storefront\StorefrontSettingService;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Storefront authZ hardenings from the API security plan.
 */
class ApiSecurityHardeningTest extends TestCase
{
    use DatabaseTransactions;

    protected int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        app(StorefrontSettingService::class)->save($this->businessId, [
            'turnstile' => [
                'site_key' => '',
                'secret_key' => null,
            ],
        ]);
        Cache::flush();

        config([
            'storefront.support_chat.enabled' => true,
            'openai.api_key' => 'sk-test-support-chat',
        ]);
    }

    private function enableTurnstile(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'turnstile' => [
                'site_key' => 'test-site-key',
                'secret_key' => 'test-secret-key',
            ],
        ]);
        Cache::flush();
    }

    public function test_inactive_contact_token_is_rejected(): void
    {
        $email = 'inactive_sec_'.uniqid().'@example.com';
        $mobile = '+2010'.random_int(10000000, 99999999);

        $register = $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Inactive',
            'last_name' => 'Sec',
            'email' => $email,
            'mobile' => $mobile,
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
        ])->assertCreated();

        $bearer = $register->json('data.token');
        $contactId = (int) $register->json('data.contact.id');

        $this->withHeader('Authorization', 'Bearer '.$bearer)
            ->getJson('/api/storefront/v1/account/profile')
            ->assertOk();

        $contact = Contact::findOrFail($contactId);
        $contact->contact_status = 'inactive';
        $contact->save();

        $this->assertSame(
            0,
            $contact->tokens()->count(),
            'Deactivating a contact must revoke Sanctum tokens'
        );

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$bearer)
            ->getJson('/api/storefront/v1/account/profile')
            ->assertUnauthorized();
    }

    public function test_customers_add_requires_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();
        Http::fake(['accounts.test/*' => Http::response(['success' => true], 201)]);
        config(['services.accounts.base' => 'https://accounts.test']);
        Cache::put('accounts_auth_token', 'test-token', 60);

        $this->postJson('/api/storefront/v1/customers/add', [
            'first_name' => 'Bot',
            'last_name' => 'Check',
            'email' => 'bot_'.uniqid().'@example.com',
            'birth_date' => '1990-05-15',
            'country' => 'EG',
            'state' => 'C',
            'mobile' => '+2010'.random_int(10000000, 99999999),
            'dial_code' => '+20',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.turnstile_token.0', 'Please complete the security check.');
    }

    public function test_track_order_requires_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();

        $this->postJson('/api/storefront/v1/track-order', [
            'invoice_no' => 'NOPE-'.uniqid(),
            'email' => 'someone@example.com',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.turnstile_token.0', 'Please complete the security check.');
    }

    public function test_escalate_rejects_foreign_order_id(): void
    {
        $locationId = (int) BusinessLocation::where('business_id', $this->businessId)
            ->where('is_active', 1)
            ->value('id');
        if ($locationId < 1) {
            $this->markTestSkipped('Active business location required.');
        }

        config([
            'storefront.support_chat.escalation_employee_id' => 1,
            'storefront.support_chat.escalation_location_id' => $locationId,
            'storefront.support_chat.escalation_created_by' => 1,
        ]);

        $ownerContact = Contact::create([
            'business_id' => $this->businessId,
            'type' => 'customer',
            'contact_status' => 'active',
            'name' => 'Order Owner',
            'first_name' => 'Order',
            'last_name' => 'Owner',
            'email' => 'owner_'.uniqid().'@example.com',
            'mobile' => '+2011'.random_int(10000000, 99999999),
            'created_by' => 1,
            'is_default' => 0,
        ]);

        $foreignOrder = Transaction::create([
            'business_id' => $this->businessId,
            'location_id' => $locationId,
            'type' => 'sell',
            'status' => 'final',
            'contact_id' => $ownerContact->id,
            'invoice_no' => 'ESC-'.uniqid(),
            'transaction_date' => now(),
            'total_before_tax' => 50,
            'final_total' => 50,
            'created_by' => 1,
            'source' => 'storefront',
            'payment_status' => 'paid',
        ]);

        $email = 'escalator_'.uniqid().'@example.com';
        $mobile = '+2010'.random_int(10000000, 99999999);
        $register = $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Esc',
            'last_name' => 'Actor',
            'email' => $email,
            'mobile' => $mobile,
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
        ])->assertCreated();

        $bearer = $register->json('data.token');

        $create = $this->withHeader('Authorization', 'Bearer '.$bearer)
            ->postJson('/api/storefront/v1/support/conversations', ['locale' => 'en'])
            ->assertCreated();
        $uuid = $create->json('data.uuid');

        $this->withHeader('Authorization', 'Bearer '.$bearer)
            ->postJson("/api/storefront/v1/support/conversations/{$uuid}/escalate", [
                'order_id' => $foreignOrder->id,
                'note' => 'should not bind',
            ])
            ->assertStatus(403);
    }

    public function test_storefront_sanctum_middleware_rejects_wrong_business_contact(): void
    {
        $contact = Contact::create([
            'business_id' => 999999,
            'type' => 'customer',
            'contact_status' => 'active',
            'name' => 'Other Biz',
            'first_name' => 'Other',
            'email' => 'otherbiz_'.uniqid().'@example.com',
            'mobile' => '+2012'.random_int(10000000, 99999999),
            'password' => Hash::make('password12345'),
            'created_by' => 1,
            'is_default' => 0,
        ]);

        $token = $contact->createToken('storefront')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/storefront/v1/account/profile')
            ->assertUnauthorized();
    }
}
