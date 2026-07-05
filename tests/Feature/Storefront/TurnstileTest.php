<?php

namespace Tests\Feature\Storefront;

use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Cloudflare Turnstile on contact and registration when configured in storefront settings.
 */
class TurnstileTest extends TestCase
{
    protected int $businessId = 1;

    protected function enableTurnstile(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'turnstile' => [
                'site_key' => 'test-site-key',
                'secret_key' => 'test-secret-key',
            ],
        ]);
        Cache::flush();
    }

    public function test_settings_exposes_turnstile_when_configured(): void
    {
        $this->enableTurnstile();

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.turnstile.enabled', true)
            ->assertJsonPath('data.turnstile.site_key', 'test-site-key')
            ->assertJsonMissingPath('data.turnstile.secret_key');
    }

    public function test_settings_turnstile_disabled_when_keys_missing(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'turnstile' => [
                'site_key' => '',
                'secret_key' => null,
            ],
        ]);
        Cache::flush();

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.turnstile.enabled', false)
            ->assertJsonPath('data.turnstile.site_key', null);
    }

    public function test_contact_requires_turnstile_when_enabled(): void
    {
        Mail::fake();
        $this->enableTurnstile();

        $this->postJson('/api/storefront/v1/contact', [
            'name' => 'Test User',
            'email' => 'visitor@example.com',
            'phone' => '+201012345678',
            'message' => 'Hello without captcha.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.turnstile_token.0', 'Please complete the security check.');
    }

    public function test_contact_accepts_valid_turnstile_token(): void
    {
        Mail::fake();
        $this->enableTurnstile();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $this->postJson('/api/storefront/v1/contact', [
            'name' => 'Test User',
            'email' => 'visitor@example.com',
            'phone' => '+201012345678',
            'message' => 'Hello with captcha.',
            'turnstile_token' => 'valid-turnstile-token',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertQueued(\App\Mail\StorefrontContactMessage::class);
    }

    public function test_register_requires_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();

        $email = 'turnstile_test_'.uniqid().'@example.com';
        $mobile = '+2010'.random_int(10000000, 99999999);

        $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'mobile' => $mobile,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.turnstile_token.0', 'Please complete the security check.');
    }

    public function test_register_accepts_valid_turnstile_token(): void
    {
        $this->enableTurnstile();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $email = 'turnstile_ok_'.uniqid().'@example.com';
        $mobile = '+2010'.random_int(10000000, 99999999);

        $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'mobile' => $mobile,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'turnstile_token' => 'valid-turnstile-token',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);
    }
}
