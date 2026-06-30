<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Product;
use App\Services\Storefront\StorefrontSettingService;
use App\StorefrontSetting;
use App\Variation;
use Tests\TestCase;

/**
 * Storefront API feature tests (catalog, auth, checkout).
 */
class StorefrontApiTest extends TestCase
{
    protected int $businessId = 1;

    public function test_ping_returns_ok(): void
    {
        $response = $this->getJson('/api/storefront/v1/ping');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.service', 'storefront-api');
    }

    public function test_products_empty_when_no_selling_locations(): void
    {
        StorefrontSetting::updateOrCreate(
            ['business_id' => $this->businessId],
            ['value' => ['selling_location_ids' => []]]
        );
        app(StorefrontSettingService::class);
        \Illuminate\Support\Facades\Cache::flush();

        $response = $this->getJson('/api/storefront/v1/products');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 0);
    }

    public function test_customer_can_register_and_login(): void
    {
        $email = 'storefront_test_'.uniqid().'@example.com';
        $mobile = '01'.random_int(100000000, 999999999);

        $register = $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => $email,
            'mobile' => $mobile,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $register->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'contact' => ['id', 'email']]]);

        $login = $this->postJson('/api/storefront/v1/auth/login', [
            'login' => $email,
            'password' => 'password123',
        ]);

        $login->assertOk()->assertJsonPath('success', true);
    }

    public function test_customer_can_reset_password_with_token(): void
    {
        $email = 'reset_test_'.uniqid().'@example.com';
        $mobile = '01'.random_int(100000000, 999999999);
        $plainToken = 'reset-token-'.uniqid();

        $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Reset',
            'last_name' => 'Test',
            'email' => $email,
            'mobile' => $mobile,
            'password' => 'oldpassword123',
            'password_confirmation' => 'oldpassword123',
        ])->assertCreated();

        \Illuminate\Support\Facades\DB::table('password_resets_contacts')->updateOrInsert(
            ['email' => $email],
            ['token' => \Illuminate\Support\Facades\Hash::make($plainToken), 'created_at' => now()]
        );

        $reset = $this->postJson('/api/storefront/v1/auth/reset-password', [
            'email' => $email,
            'token' => $plainToken,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $reset->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'Password updated successfully.');

        $this->postJson('/api/storefront/v1/auth/login', [
            'login' => $email,
            'password' => 'newpassword123',
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/storefront/v1/auth/login', [
            'login' => $email,
            'password' => 'oldpassword123',
        ])->assertStatus(422);
    }

    public function test_settings_contact_does_not_expose_raw_email(): void
    {
        $email = 'contact_'.uniqid().'@example.com';

        app(StorefrontSettingService::class)->save($this->businessId, [
            'contact' => [
                'phone' => '01000000000',
                'email' => $email,
                'whatsapp' => '',
            ],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $response = $this->getJson('/api/storefront/v1/settings');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.contact.email')
            ->assertJsonPath('data.contact.email_encoded', base64_encode($email));
    }

    public function test_locations_do_not_expose_raw_email(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->first();
        if (empty($location)) {
            $this->markTestSkipped('No business location in database.');
        }

        $testEmail = 'loc_'.uniqid().'@example.com';
        $location->email = $testEmail;
        $location->is_active = 1;
        $location->save();

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $response = $this->getJson('/api/storefront/v1/locations');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $rows = $response->json('data');
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        $match = collect($rows)->first(fn ($row) => (int) ($row['id'] ?? 0) === (int) $location->id);
        $this->assertNotNull($match, 'Expected location to appear in /locations response.');
        $this->assertArrayNotHasKey('email', $match);
        $this->assertSame(base64_encode($testEmail), $match['email_encoded']);
    }

    public function test_availability_endpoint_structure(): void
    {
        $location = BusinessLocation::where('business_id', $this->businessId)->first();
        if (empty($location)) {
            $this->markTestSkipped('No business location in database.');
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

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

        $url = '/api/storefront/v1/products/'.$product->id.'/availability?variation_id='.$variation->id;
        $response = $this->getJson($url);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'in_stock_count',
                    'cod_available',
                    'locations',
                ],
            ]);
    }
}
