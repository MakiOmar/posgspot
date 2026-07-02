<?php

namespace Tests\Feature\Storefront;

use App\Contact;
use App\Utils\ContactUtil;
use Tests\TestCase;

class AddCustomerApiTest extends TestCase
{
    protected int $businessId = 1;

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

    public function test_add_customer_rejects_duplicate_mobile(): void
    {
        $mobile = '+2011'.random_int(10000000, 99999999);

        $this->postJson('/api/storefront/v1/customers/add', [
            'first_name' => 'Original',
            'last_name' => 'Name',
            'email' => 'first_'.uniqid().'@example.com',
            'birth_date' => '1985-01-01',
            'country' => 'EG',
            'state' => 'GZ',
            'mobile' => $mobile,
            'dial_code' => '+20',
        ])->assertCreated();

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
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mobile']);
    }

    public function test_add_customer_rejects_mobile_stored_without_dial_code(): void
    {
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
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mobile']);
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
