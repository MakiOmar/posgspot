<?php

namespace Tests\Feature\Storefront;

use App\StorefrontDeviceToken;
use Tests\TestCase;

/**
 * Storefront mobile device token registration.
 */
class DeviceTokenTest extends TestCase
{
    private function registerAndLogin(): array
    {
        $email = 'device_test_'.uniqid().'@example.com';
        $mobile = '+2010'.random_int(10000000, 99999999);

        $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Device',
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

    public function test_register_and_unregister_device_token(): void
    {
        $auth = $this->registerAndLogin();
        $headers = ['Authorization' => 'Bearer '.$auth['token']];
        $pushToken = 'fcm-test-token-'.uniqid();

        $this->postJson('/api/storefront/v1/account/devices', [
            'platform' => 'android',
            'token' => $pushToken,
            'locale' => 'en',
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.platform', 'android');

        $this->assertDatabaseHas('storefront_device_tokens', [
            'contact_id' => $auth['contact_id'],
            'token' => $pushToken,
            'platform' => 'android',
        ]);

        // Idempotent re-register
        $this->postJson('/api/storefront/v1/account/devices', [
            'platform' => 'android',
            'token' => $pushToken,
            'locale' => 'ar',
        ], $headers)->assertCreated();

        $this->assertSame(1, StorefrontDeviceToken::where('token', $pushToken)->count());

        $this->deleteJson('/api/storefront/v1/account/devices/'.urlencode($pushToken), [], $headers)
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('storefront_device_tokens', [
            'token' => $pushToken,
        ]);
    }

    public function test_devices_require_auth(): void
    {
        $this->postJson('/api/storefront/v1/account/devices', [
            'platform' => 'ios',
            'token' => 'x',
        ])->assertUnauthorized();
    }
}
