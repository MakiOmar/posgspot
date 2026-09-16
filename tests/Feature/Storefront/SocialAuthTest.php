<?php

namespace Tests\Feature\Storefront;

use App\Contact;
use App\Services\Storefront\SocialAuthService;
use App\Services\Storefront\StorefrontSettingService;
use App\StorefrontSocialIdentity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteTwoUser;
use Mockery;
use Tests\TestCase;

/**
 * Storefront Socialite Google/Facebook login, link, and disconnect.
 */
class SocialAuthTest extends TestCase
{
    protected int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('storefront.social_login.google.enabled', true);
        Config::set('storefront.social_login.facebook.enabled', true);
        Config::set('services.google.client_id', 'test-google-client');
        Config::set('services.google.client_secret', 'test-google-secret');
        Config::set('services.facebook.client_id', 'test-fb-client');
        Config::set('services.facebook.client_secret', 'test-fb-secret');

        app(StorefrontSettingService::class)->save($this->businessId, [
            'turnstile' => ['site_key' => '', 'secret_key' => null],
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_disabled_provider_returns_404(): void
    {
        Config::set('storefront.social_login.google.enabled', false);

        $this->getJson('/api/storefront/v1/auth/social/google/redirect?format=json')
            ->assertNotFound();
    }

    public function test_token_login_creates_verified_contact(): void
    {
        $this->mockSocialiteUserFromToken($this->makeSocialUser([
            'id' => 'gid-new-'.uniqid(),
            'email' => 'social_new_'.uniqid().'@example.com',
            'name' => 'Social New',
            'avatar' => 'https://example.com/a.png',
            'user' => ['email_verified' => true],
        ]));

        $response = $this->postJson('/api/storefront/v1/auth/social/google/token', [
            'access_token' => 'fake-access-token',
        ])->assertOk();

        $response->assertJsonPath('data.contact.email_verified', true);
        $this->assertNotEmpty($response->json('data.token'));

        $contactId = (int) $response->json('data.contact.id');
        $this->assertDatabaseHas('storefront_social_identities', [
            'contact_id' => $contactId,
            'provider' => 'google',
        ]);
    }

    public function test_token_login_refuses_existing_password_account(): void
    {
        $email = 'exists_'.uniqid().'@example.com';
        $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Existing',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->mockSocialiteUserFromToken($this->makeSocialUser([
            'id' => 'gid-takeover-'.uniqid(),
            'email' => $email,
            'name' => 'Hacker',
            'user' => ['email_verified' => true],
        ]));

        $this->postJson('/api/storefront/v1/auth/social/google/token', [
            'access_token' => 'fake-access-token',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['account_exists']);
    }

    public function test_link_while_authenticated_binds_identity(): void
    {
        $register = $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Linker',
            'email' => 'link_'.uniqid().'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $token = $register->json('data.token');
        $contactId = (int) $register->json('data.contact.id');
        $providerUid = 'gid-link-'.uniqid();

        $this->mockSocialiteUserFromToken($this->makeSocialUser([
            'id' => $providerUid,
            'email' => 'other_'.uniqid().'@example.com',
            'name' => 'Linked',
            'user' => ['email_verified' => true],
        ]));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/storefront/v1/auth/social/google/token', [
                'access_token' => 'fake-access-token',
                'intent' => 'link',
            ])
            ->assertOk();

        $this->assertDatabaseHas('storefront_social_identities', [
            'contact_id' => $contactId,
            'provider' => 'google',
            'provider_user_id' => $providerUid,
        ]);
    }

    public function test_disconnect_blocked_without_password_and_sole_identity(): void
    {
        $email = 'solo_'.uniqid().'@example.com';
        $uid = 'gid-solo-'.uniqid();

        $this->mockSocialiteUserFromToken($this->makeSocialUser([
            'id' => $uid,
            'email' => $email,
            'name' => 'Solo',
            'user' => ['email_verified' => true],
        ]));

        $login = $this->postJson('/api/storefront/v1/auth/social/google/token', [
            'access_token' => 'fake-access-token',
        ])->assertOk();

        $token = $login->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/storefront/v1/account/social/google')
            ->assertStatus(422);
    }

    public function test_exchange_code_is_single_use(): void
    {
        $service = app(SocialAuthService::class);
        $contact = Contact::create([
            'business_id' => $this->businessId,
            'type' => 'customer',
            'contact_status' => 'active',
            'name' => 'Exchange',
            'first_name' => 'Exchange',
            'email' => 'ex_'.uniqid().'@example.com',
            'mobile' => '',
            'created_by' => 1,
            'email_verified_at' => now(),
        ]);

        $session = (new \ReflectionClass($service))
            ->getMethod('issueSession');
        $session->setAccessible(true);
        $payload = $session->invoke($service, $contact);

        $store = (new \ReflectionClass($service))->getMethod('storeExchangePayload');
        $store->setAccessible(true);
        $code = $store->invoke($service, $payload);

        $this->postJson('/api/storefront/v1/auth/social/exchange', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('data.contact.id', $contact->id);

        $this->postJson('/api/storefront/v1/auth/social/exchange', ['code' => $code])
            ->assertStatus(422);
    }

    public function test_settings_exposes_social_login_flags(): void
    {
        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.social_login.google_enabled', true)
            ->assertJsonPath('data.social_login.facebook_enabled', true);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makeSocialUser(array $attrs): SocialiteTwoUser
    {
        $user = new SocialiteTwoUser();
        $user->id = (string) $attrs['id'];
        $user->email = (string) ($attrs['email'] ?? '');
        $user->name = (string) ($attrs['name'] ?? '');
        $user->avatar = (string) ($attrs['avatar'] ?? '');
        $user->user = is_array($attrs['user'] ?? null) ? $attrs['user'] : [];

        return $user;
    }

    private function mockSocialiteUserFromToken(SocialiteUserContract $user): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('userFromToken')->andReturn($user);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }
}
