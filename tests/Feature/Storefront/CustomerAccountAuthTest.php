<?php

namespace Tests\Feature\Storefront;

use App\Contact;
use App\Mail\StorefrontEmailVerification;
use App\Mail\StorefrontPasswordReset;
use App\Services\Storefront\OneTimeCodeAttempts;
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

        Mail::assertSent(StorefrontEmailVerification::class);

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

    public function test_guest_verify_never_returns_profile_and_requires_a_valid_code(): void
    {
        Mail::fake();

        $email = 'verify_guest_'.uniqid().'@example.com';
        $register = $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Guest',
            'last_name' => 'Verify',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $contact = Contact::findOrFail((int) $register->json('data.contact.id'));
        $contact->email_verify_code_hash = Hash::make('654321');
        $contact->email_verify_expires_at = now()->addMinutes(30);
        $contact->save();

        $this->postJson('/api/storefront/v1/auth/email/verify', ['email' => $email, 'code' => '654321'])
            ->assertOk()
            ->assertJsonMissingPath('data.contact');

        // Already verified: a guess must not succeed or leak the profile.
        $this->postJson('/api/storefront/v1/auth/email/verify', ['email' => $email, 'code' => '000000'])
            ->assertStatus(422)
            ->assertJsonMissingPath('data.contact');
    }

    public function test_profile_cannot_claim_another_customers_mobile(): void
    {
        Mail::fake();

        $victimMobile = '+2010'.random_int(10000000, 99999999);
        $attackerMobile = '+2011'.random_int(10000000, 99999999);
        $responses = [];
        foreach ([['victim', $victimMobile], ['attacker', $attackerMobile]] as [$name, $mobile]) {
            $responses[$name] = $this->postJson('/api/storefront/v1/auth/register', [
                'first_name' => ucfirst($name),
                'last_name' => 'Mobile',
                'email' => $name.'_mobile_'.uniqid().'@example.com',
                'mobile' => $mobile,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])->assertCreated();
        }
        $bearer = 'Bearer '.$responses['attacker']->json('data.token');

        $this->withHeader('Authorization', $bearer)
            ->putJson('/api/storefront/v1/account/profile', ['mobile' => $victimMobile])
            ->assertStatus(422)
            ->assertJsonPath('errors.mobile.0', 'Mobile already registered.');

        $this->withHeader('Authorization', $bearer)
            ->putJson('/api/storefront/v1/account/profile', ['mobile' => $attackerMobile, 'first_name' => 'Renamed'])
            ->assertOk();
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

    public function test_avatar_upload_replace_and_delete(): void
    {
        Mail::fake();

        $email = 'avatar_'.uniqid().'@example.com';
        $register = $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Avatar',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $token = $register->json('data.token');
        $this->assertNull($register->json('data.contact.avatar_url'));

        $file = \Illuminate\Http\UploadedFile::fake()->image('me.jpg', 120, 120);

        $upload = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/storefront/v1/account/profile/avatar', [
                'avatar' => $file,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk();

        $avatarUrl = $upload->json('data.avatar_url');
        $this->assertNotEmpty($avatarUrl);

        $replace = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/storefront/v1/account/profile/avatar', [
                'avatar' => \Illuminate\Http\UploadedFile::fake()->image('me2.png', 80, 80),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk();

        $this->assertNotEmpty($replace->json('data.avatar_url'));
        $this->assertSame(
            1,
            Contact::find((int) $register->json('data.contact.id'))->media()->count()
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/storefront/v1/account/profile/avatar')
            ->assertOk()
            ->assertJsonPath('data.avatar_url', null);

        $this->getJson('/api/storefront/v1/account/profile/avatar')
            ->assertUnauthorized();
    }

    public function test_forgot_password_sends_six_digit_code_that_resets(): void
    {
        Mail::fake();

        $email = 'reset_otp_'.uniqid().'@example.com';
        $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Reset',
            'last_name' => 'Code',
            'email' => $email,
            'mobile' => '+2010'.random_int(10000000, 99999999),
            'password' => 'oldpassword123',
            'password_confirmation' => 'oldpassword123',
        ])->assertCreated();

        $this->postJson('/api/storefront/v1/auth/forgot-password', [
            'email' => $email,
        ])->assertOk();

        $code = null;
        Mail::assertSent(StorefrontPasswordReset::class, function (StorefrontPasswordReset $mail) use (&$code) {
            $code = $mail->token;

            return strlen($mail->token) === 6 && ctype_digit($mail->token);
        });

        $this->assertNotEmpty($code);

        $this->postJson('/api/storefront/v1/auth/reset-password', [
            'email' => $email,
            'token' => $code,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $this->postJson('/api/storefront/v1/auth/login', [
            'login' => $email,
            'password' => 'newpassword123',
        ])->assertOk();
    }

    public function test_reset_code_is_burned_after_repeated_wrong_guesses(): void
    {
        Mail::fake();

        $email = 'reset_lock_'.uniqid().'@example.com';
        $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Reset',
            'last_name' => 'Lock',
            'email' => $email,
            'password' => 'oldpassword123',
            'password_confirmation' => 'oldpassword123',
        ])->assertCreated();
        $this->postJson('/api/storefront/v1/auth/forgot-password', ['email' => $email])->assertOk();

        $code = null;
        Mail::assertSent(StorefrontPasswordReset::class, function (StorefrontPasswordReset $mail) use (&$code) {
            $code = $mail->token;

            return true;
        });
        $wrong = $code === '000000' ? '111111' : '000000';
        $attempt = fn (string $token) => $this->postJson('/api/storefront/v1/auth/reset-password', [
            'email' => $email,
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        for ($i = 0; $i < OneTimeCodeAttempts::MAX_FAILURES; $i++) {
            $attempt($wrong)->assertStatus(422);
        }

        $attempt($code)->assertStatus(422);
    }

    public function test_verify_code_is_burned_after_repeated_wrong_guesses(): void
    {
        Mail::fake();

        $email = 'verify_lock_'.uniqid().'@example.com';
        $register = $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Verify',
            'last_name' => 'Lock',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $contact = Contact::findOrFail((int) $register->json('data.contact.id'));
        $contact->email_verify_code_hash = Hash::make('654321');
        $contact->email_verify_expires_at = now()->addMinutes(30);
        $contact->save();

        for ($i = 0; $i < OneTimeCodeAttempts::MAX_FAILURES; $i++) {
            $this->postJson('/api/storefront/v1/auth/email/verify', ['email' => $email, 'code' => '000000'])
                ->assertStatus(422);
        }

        $this->postJson('/api/storefront/v1/auth/email/verify', ['email' => $email, 'code' => '654321'])
            ->assertStatus(422);
        $this->assertNull($contact->fresh()->email_verified_at);
    }

    public function test_authenticated_unverified_customer_cannot_checkout(): void
    {
        Mail::fake();

        $register = $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Unverified',
            'email' => 'unverified_'.uniqid().'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $token = $register->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/storefront/v1/checkout', [
                'idempotency_key' => 'SF-UNVERIFIED-'.uniqid(),
                'location_id' => 1,
                'payment_method' => 'cod',
                'items' => [
                    ['variation_id' => 1, 'quantity' => 1],
                ],
                'shipping_rate_id' => 'placeholder',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['verification']);
    }
}
