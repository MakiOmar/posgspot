<?php

namespace Tests\Feature\Storefront;

use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    protected int $businessId = 1;

    protected function enableMailchimp(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'newsletter' => [
                'enabled' => true,
                'provider' => 'mailchimp',
                'double_opt_in' => true,
                'mailchimp' => [
                    'api_key' => 'testkeytestkeytestkeytestkeytest-us1',
                    'audience_id' => 'aud123',
                ],
            ],
        ]);
        Cache::flush();
    }

    public function test_settings_exposes_newsletter_enabled_when_configured(): void
    {
        $this->enableMailchimp();

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.newsletter.enabled', true)
            ->assertJsonMissingPath('data.newsletter.mailchimp');
    }

    public function test_settings_newsletter_disabled_without_credentials(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'newsletter' => [
                'enabled' => true,
                'provider' => 'mailchimp',
                'mailchimp' => [
                    'api_key' => null,
                    'audience_id' => '',
                ],
            ],
        ]);
        Cache::flush();

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.newsletter.enabled', false);
    }

    public function test_subscribe_unavailable_when_disabled(): void
    {
        $this->postJson('/api/storefront/v1/newsletter/subscribe', [
            'email' => 'shopper@example.com',
        ])
            ->assertStatus(503)
            ->assertJsonPath('success', false);
    }

    public function test_subscribe_validates_email(): void
    {
        $this->enableMailchimp();

        $this->postJson('/api/storefront/v1/newsletter/subscribe', [
            'email' => 'not-an-email',
        ])
            ->assertStatus(422);
    }

    public function test_subscribe_mailchimp_success(): void
    {
        $this->enableMailchimp();

        Http::fake([
            'us1.api.mailchimp.com/*' => Http::response(['status' => 'pending'], 200),
        ]);

        $this->postJson('/api/storefront/v1/newsletter/subscribe', [
            'email' => 'shopper@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'lists/aud123/members/')
                && $request['email_address'] === 'shopper@example.com'
                && $request['status_if_new'] === 'pending';
        });
    }

    public function test_subscribe_mailerlite_success(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'newsletter' => [
                'enabled' => true,
                'provider' => 'mailerlite',
                'double_opt_in' => false,
                'mailerlite' => [
                    'api_token' => 'ml-token-test',
                    'group_id' => 'group1',
                ],
            ],
        ]);
        Cache::flush();

        Http::fake([
            'connect.mailerlite.com/*' => Http::response(['data' => ['id' => '1']], 201),
        ]);

        $this->postJson('/api/storefront/v1/newsletter/subscribe', [
            'email' => 'shopper@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'subscribed');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://connect.mailerlite.com/api/subscribers'
                && $request['email'] === 'shopper@example.com'
                && $request['status'] === 'active'
                && $request['groups'] === ['group1'];
        });
    }
}
