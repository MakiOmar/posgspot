<?php

namespace Tests\Feature\Storefront;

use App\Contact;
use App\Jobs\SyncCustomerToAccounts;
use App\Services\Storefront\Accounts\AccountsCustomerSync;
use App\Utils\ContactUtil;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddCustomerApiTest extends TestCase
{
    protected int $businessId = 1;

    private const ACCOUNTS_CUSTOMERS = 'accounts.test/api/customers';

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([SyncCustomerToAccounts::class]);
        config(['services.accounts.base' => 'https://accounts.test']);
        Cache::put('accounts_auth_token', 'test-token', 60);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'add_customer_'.uniqid().'@example.com',
            'birth_date' => '1990-05-15',
            'country' => 'EG',
            'state' => 'C',
            'mobile' => '+2010'.random_int(10000000, 99999999),
            'dial_code' => '+20',
        ], $overrides);
    }

    private function fakeAccounts(int $status = 201, array $body = ['success' => true]): void
    {
        Http::fake([self::ACCOUNTS_CUSTOMERS => Http::response($body, $status)]);
    }

    public function test_add_customer_creates_contact_in_pos_and_accounts(): void
    {
        $this->fakeAccounts();
        $payload = $this->payload(['first_name' => 'Sync', 'last_name' => 'Customer']);

        $this->postJson('/api/storefront/v1/customers/add', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', true);

        $this->assertDatabaseHas('contacts', [
            'mobile' => $payload['mobile'],
            'type' => 'customer',
            'country' => 'EG',
            'state' => 'C',
        ]);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://'.self::ACCOUNTS_CUSTOMERS
            && $request['phone'] === $payload['mobile']
            && $request['email'] === $payload['email']
            && $request['name'] === 'Sync Customer'
            && $request['update_existing'] === false);
        Bus::assertNotDispatched(SyncCustomerToAccounts::class);
    }

    public function test_add_customer_rejects_email_registered_in_accounts_under_another_phone(): void
    {
        $this->fakeAccounts(422, ['success' => false, 'errors' => ['email' => ['The email has already been taken.']]]);
        $payload = $this->payload();

        $this->postJson('/api/storefront/v1/customers/add', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseMissing('contacts', ['mobile' => $payload['mobile']]);
    }

    public function test_add_customer_still_creates_pos_contact_and_queues_retry_when_accounts_is_down(): void
    {
        $this->fakeAccounts(502, ['message' => 'Bad gateway']);
        $payload = $this->payload();

        $this->postJson('/api/storefront/v1/customers/add', $payload)->assertCreated();

        $this->assertDatabaseHas('contacts', ['mobile' => $payload['mobile']]);
        Bus::assertDispatched(SyncCustomerToAccounts::class, fn ($job) => $job->phone === $payload['mobile']
            && $job->email === $payload['email']);
    }

    public function test_add_customer_rejects_invalid_phone(): void
    {
        $this->fakeAccounts();

        $this->postJson('/api/storefront/v1/customers/add', $this->payload(['mobile' => '+201812345678']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mobile']);

        Http::assertNothingSent();
    }

    public function test_add_customer_existing_mobile_syncs_stored_contact_without_leaking_it(): void
    {
        $this->fakeAccounts();
        $mobile = '+2011'.random_int(10000000, 99999999);
        $storedEmail = 'first_'.uniqid().'@example.com';

        $this->postJson('/api/storefront/v1/customers/add', $this->payload([
            'first_name' => 'Original',
            'last_name' => 'Name',
            'email' => $storedEmail,
            'mobile' => $mobile,
        ]))->assertCreated();

        $this->postJson('/api/storefront/v1/customers/add', $this->payload([
            'first_name' => 'Another',
            'last_name' => 'Person',
            'mobile' => $mobile,
        ]))
            ->assertOk()
            ->assertJsonPath('data.created', false)
            ->assertJsonPath('data.contact', null);

        $this->assertSame(1, Contact::where('business_id', $this->businessId)->where('mobile', $mobile)->count());
        Http::assertSentCount(2);
        Http::assertSent(fn (HttpRequest $request) => $request['email'] === $storedEmail && $request['name'] === 'Original Name');
    }

    public function test_add_customer_matches_mobile_stored_without_dial_code(): void
    {
        $this->fakeAccounts();
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

        $this->postJson('/api/storefront/v1/customers/add', $this->payload(['mobile' => '+20'.$national]))
            ->assertOk()
            ->assertJsonPath('data.created', false);

        $this->assertDatabaseMissing('contacts', ['mobile' => '+20'.$national]);
        Http::assertSent(fn (HttpRequest $request) => $request['phone'] === $storedMobile);
    }

    public function test_add_customer_rejects_duplicate_email_case_insensitively(): void
    {
        $this->fakeAccounts();
        $email = 'duplicate_'.uniqid().'@example.com';

        $this->postJson('/api/storefront/v1/customers/add', $this->payload(['email' => $email]))->assertCreated();

        $this->postJson('/api/storefront/v1/customers/add', $this->payload([
            'email' => '  '.strtoupper($email).' ',
            'mobile' => '+2011'.random_int(10000000, 99999999),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_add_customer_stores_email_lowercased(): void
    {
        $this->fakeAccounts();
        $email = 'Mixed_'.uniqid().'@Example.com';
        $payload = $this->payload(['email' => $email]);

        $this->postJson('/api/storefront/v1/customers/add', $payload)->assertCreated();

        $this->assertDatabaseHas('contacts', ['mobile' => $payload['mobile'], 'email' => strtolower($email)]);
    }

    public function test_sync_job_retries_only_when_accounts_is_unavailable(): void
    {
        $job = new SyncCustomerToAccounts('Sync Customer', 'sync@example.com', '+201012345678');

        Http::fake([self::ACCOUNTS_CUSTOMERS => Http::sequence()
            ->push(['success' => false, 'errors' => ['email' => ['taken']]], 422)
            ->push('Bad gateway', 502)]);
        $job->handle(app(AccountsCustomerSync::class));

        $this->expectException(\RuntimeException::class);
        $job->handle(app(AccountsCustomerSync::class));
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
