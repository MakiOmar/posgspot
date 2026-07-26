<?php

namespace Tests\Feature\Storefront;

use App\Contact;
use App\Mail\StorefrontEmailVerification;
use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Storefront email verification, password change, and deletion request.
 */
class CustomerAccountAuthTest extends TestCase
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

    public function test_register_without_mobile_sends_verification_and_can_verify(): void
    {
        Mail::fake();

        $email = 'verify_'.uniqid().'@example.com';

        $register = $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Verify',
            'last_name' => 'User',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $register->assertJsonPath('data.contact.email_verified', false);
        $token = $register->json('data.token');
        $contactId = (int) $register->json('data.contact.id');

        Mail::assertQueued(StorefrontEmailVerification::class);

        $contact = Contact::findOrFail($contactId);
        $this->assertNotEmpty($contact->email_verify_code_hash);

        // Inject a known OTP for assertion.
        $code = '123456';
        $contact->email_verify_code_hash = Hash::make($code);
        $contact->email_verify_expires_at = now()->addMinutes(30);
        $contact->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/storefront/v1/auth/email/verify', [
                'code' => $code,
            ])
            ->assertOk()
            ->assertJsonPath('data.contact.email_verified', true);

        $this->assertNotNull($contact->fresh()->email_verified_at);
    }

    public function test_change_password_issues_new_token(): void
    {
        Mail::fake();

        $email = 'pwd_'.uniqid().'@example.com';
        $register = $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Pwd',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $oldToken = $register->json('data.token');

        $change = $this->withHeader('Authorization', 'Bearer '.$oldToken)
            ->putJson('/api/storefront/v1/account/password', [
                'current_password' => 'password123',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertOk();

        $newToken = $change->json('data.token');
        $this->assertNotEmpty($newToken);
        $this->assertNotSame($oldToken, $newToken);

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$oldToken)
            ->getJson('/api/storefront/v1/account/profile')
            ->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer '.$newToken)
            ->getJson('/api/storefront/v1/account/profile')
            ->assertOk();
    }

    public function test_delete_request_sets_flag(): void
    {
        Mail::fake();

        $email = 'del_'.uniqid().'@example.com';
        $register = $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Del',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $token = $register->json('data.token');
        $contactId = (int) $register->json('data.contact.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/storefront/v1/account/delete-request')
            ->assertOk()
            ->assertJsonPath('data.contact.delete_requested', true);

        $this->assertNotNull(Contact::find($contactId)->storefront_delete_requested_at);
    }
}
