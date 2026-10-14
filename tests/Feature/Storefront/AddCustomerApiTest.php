<?php

namespace Tests\Feature\Storefront;

use App\Contact;
use App\Jobs\SyncCustomerToAccounts;
use App\Services\Storefront\Accounts\AccountsApiClient;
use App\Utils\ContactUtil;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddCustomerApiTest extends TestCase
{
    protected int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([SyncCustomerToAccounts::class]);
    }

    public function test_add_customer_creates_contact(): void
    {
        $mobile = '+2010'.random_int(10000000, 99999999);

        $response = $this->postJson('/api/storefront/v1/customers/add', [
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'add_customer_'.uniqid().'@example.com',
            'birth_date' => '1990-05-15',
            'country' => 'EG',
            'state' => 'C',
            'mobile' => $mobile,
            'dial_code' => '+20',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', true);

        $this->assertDatabaseHas('contacts', [
            'mobile' => $mobile,
            'type' => 'customer',
            'country' => 'EG',
            'state' => 'C',
        ]);
    }

    public function test_add_customer_rejects_invalid_phone(): void
    {
        $response = $this->postJson('/api/storefront/v1/customers/add', [
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'invalid_phone_'.uniqid().'@example.com',
            'birth_date' => '1990-05-15',
            'country' => 'EG',
            'state' => 'C',
            'mobile' => '+201812345678',
            'dial_code' => '+20',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mobile']);
    }

    public function test_add_customer_queues_accounts_sync_for_new_contact(): void
    {
        Bus::fake([SyncCustomerToAccounts::class]);
        $mobile = '+2010'.random_int(10000000, 99999999);
        $email = 'sync_'.uniqid().'@example.com';

        $this->postJson('/api/storefront/v1/customers/add', [
            'first_name' => 'Sync',
            'last_name' => 'Customer',
            'email' => $email,
            'birth_date' => '1990-05-15',
            'country' => 'EG',
            'state' => 'C',
            'mobile' => $mobile,
            'dial_code' => '+20',
        ])->assertCreated();

        Bus::assertDispatched(SyncCustomerToAccounts::class, fn ($job) => $job->phone === $mobile
            && $job->email === $email
            && $job->name === 'Sync Customer');
    }

    public function test_add_customer_existing_mobile_syncs_stored_contact_without_leaking_it(): void
    {
        $mobile = '+2011'.random_int(10000000, 99999999);
        $storedEmail = 'first_'.uniqid().'@example.com';

        $this->postJson('/api/storefront/v1/customers/add', [
            'first_name' => 'Original',
            'last_name' => 'Name',
            'email' => $storedEmail,
            'birth_date' => '1985-01-01',
            'country' => 'EG',
            'state' => 'GZ',
            'mobile' => $mobile,
            'dial_code' => '+20',
        ])->assertCreated();

        Bus::fake([SyncCustomerToAccounts::class]);

        $this->postJson('/api/storefront/v1/customers/add', [
            'first_name' => 'Another',
            'last_name' => 'Person',
            'email' => 'second_'.uniqid().'@example.com',
            'birth_date' => '1985-01-01',
            'country' => 'EG',
            'state' => 'C',
            'mobile' => $mobile,
            'dial_code' => '+20',
        ])
            ->assertOk()
            ->assertJsonPath('data.created', false)
            ->assertJsonPath('data.contact', null);

        $this->assertSame(1, Contact::where('business_id', $this->businessId)->where('mobile', $mobile)->count());
        Bus::assertDispatched(SyncCustomerToAccounts::class, fn ($job) => $job->email === $storedEmail
            && $job->name === 'Original Name');
    }

    public function test_sync_job_posts_customer_to_accounts_without_updating_existing(): void
    {
        config(['services.accounts.base' => 'https://accounts.test']);
        Cache::put('accounts_auth_token', 'test-token', 60);
        Http::fake(['accounts.test/api/customers' => Http::response(['success' => true], 201)]);

        (new SyncCustomerToAccounts('Sync Customer', 'sync@example.com', '+201012345678'))
            ->handle(app(AccountsApiClient::class));

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://accounts.test/api/customers'
            && $request['phone'] === '+201012345678'
            && $request['email'] === 'sync@example.com'
            && $request['update_existing'] === false
            && $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_sync_job_throws_for_retry_when_accounts_is_down(): void
    {
        config(['services.accounts.base' => 'https://accounts.test']);
        Cache::put('accounts_auth_token', 'test-token', 60);
        Http::fake(['accounts.test/api/customers' => Http::response('Bad gateway', 502)]);

        $this->expectException(\RuntimeException::class);

        (new SyncCustomerToAccounts('Sync Customer', 'sync@example.com', '+201012345678'))
            ->handle(app(AccountsApiClient::class));
    }

    public function test_sync_job_does_not_retry_when_accounts_rejects_data(): void
    {
        config(['services.accounts.base' => 'https://accounts.test']);
        Cache::put('accounts_auth_token', 'test-token', 60);
        Http::fake(['accounts.test/api/customers' => Http::response(['success' => false, 'message' => 'Email already exists'], 422)]);

        (new SyncCustomerToAccounts('Sync Customer', 'taken@example.com', '+201012345678'))
            ->handle(app(AccountsApiClient::class));

        Http::assertSentCount(1);
    }

    public function test_add_customer_matches_mobile_stored_without_dial_code(): void
    {
        Bus::fake([SyncCustomerToAccounts::class]);
        $national = '10'.random_int(10000000, 99999999);
        $storedMobile = '0'.$national;

        app(ContactUtil::class)->createNewContact([
            'business_id' => $this->businessId,
            'type' => 'customer',
            'contact_status' => 'active',
            'name' => 'Legacy Customer',
            'first_name' => 'Legacy',
            'last_name' => 'Customer',
            'email' => 'legacy_'.uniqid().'@example.com',
            'mobile' => $storedMobile,
            'created_by' => 1,
        ]);

        $this->postJson('/api/storefront/v1/customers/add', [
            'first_name' => 'New',
            'last_name' => 'Signup',
            'email' => 'new_'.uniqid().'@example.com',
            'birth_date' => '1990-01-01',
            'country' => 'EG',
            'state' => 'C',
            'mobile' => '+20'.$national,
            'dial_code' => '+20',
        ])
            ->assertOk()
            ->assertJsonPath('data.created', false);

        $this->assertDatabaseMissing('contacts', ['mobile' => '+20'.$national]);
        Bus::assertDispatched(SyncCustomerToAccounts::class, fn ($job) => $job->phone === $storedMobile);
    }

    public function test_add_customer_rejects_duplicate_email(): void
    {
        $email = 'duplicate_'.uniqid().'@example.com';

        $this->postJson('/api/storefront/v1/customers/add', [
            'first_name' => 'First',
            'last_name' => 'User',
            'email' => $email,
            'birth_date' => '1990-01-01',
            'country' => 'EG',
            'state' => 'C',
            'mobile' => '+2010'.random_int(10000000, 99999999),
            'dial_code' => '+20',
        ])->assertCreated();

        $this->postJson('/api/storefront/v1/customers/add', [
            'first_name' => 'Second',
            'last_name' => 'User',
            'email' => $email,
            'birth_date' => '1991-02-02',
            'country' => 'EG',
            'state' => 'GZ',
            'mobile' => '+2011'.random_int(10000000, 99999999),
            'dial_code' => '+20',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_phone_countries_endpoint(): void
    {
        $response = $this->getJson('/api/storefront/v1/phone-countries');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    public function test_geo_countries_and_states(): void
    {
        $this->getJson('/api/storefront/v1/geo/countries')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/storefront/v1/geo/states/EG')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['code' => 'C', 'name' => 'Cairo']);
    }
}
