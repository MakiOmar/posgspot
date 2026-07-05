<?php

namespace Tests\Feature\Storefront;

use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Storefront Sanctum session lifetime and revocation.
 */
class SanctumSessionTest extends TestCase
{
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
    }

    public function test_password_reset_revokes_existing_sanctum_tokens(): void
    {
        $email = 'sanctum_revoke_'.uniqid().'@example.com';
        $mobile = '+2010'.random_int(10000000, 99999999);
        $plainToken = 'reset-token-'.uniqid();

        $register = $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Sanctum',
            'last_name' => 'Revoke',
            'email' => $email,
            'mobile' => $mobile,
            'password' => 'oldpassword123',
            'password_confirmation' => 'oldpassword123',
        ])->assertCreated();

        $bearer = $register->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$bearer)
            ->getJson('/api/storefront/v1/account/profile')
            ->assertOk();

        DB::table('password_resets_contacts')->updateOrInsert(
            ['email' => $email],
            ['token' => \Illuminate\Support\Facades\Hash::make($plainToken), 'created_at' => now()]
        );

        $this->postJson('/api/storefront/v1/auth/reset-password', [
            'email' => $email,
            'token' => $plainToken,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $contactId = (int) $register->json('data.contact.id');
        $this->assertSame(
            0,
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $contactId)
                ->where('tokenable_type', 'App\\Contact')
                ->count()
        );

        // HTTP tests reuse the app container; clear cached guards so the next
        // request re-validates the bearer token instead of a stale user.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$bearer)
            ->getJson('/api/storefront/v1/account/profile')
            ->assertUnauthorized();
    }

    public function test_expired_sanctum_token_is_rejected(): void
    {
        config(['sanctum.expiration' => 60]);

        $email = 'sanctum_expired_'.uniqid().'@example.com';
        $mobile = '+2010'.random_int(10000000, 99999999);

        $register = $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Sanctum',
            'last_name' => 'Expired',
            'email' => $email,
            'mobile' => $mobile,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $bearer = $register->json('data.token');
        $contactId = (int) $register->json('data.contact.id');

        DB::table('personal_access_tokens')
            ->where('tokenable_id', $contactId)
            ->where('tokenable_type', 'App\\Contact')
            ->update(['created_at' => now()->subMinutes(61)]);

        $this->withHeader('Authorization', 'Bearer '.$bearer)
            ->getJson('/api/storefront/v1/account/profile')
            ->assertUnauthorized();
    }
}
