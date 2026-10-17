<?php

namespace Tests\Feature\Storefront;

use App\Business;
use App\Contact;
use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Sync storefront auth emails must go out through the business mail settings (POS Business
 * Settings → Email), not whatever mailer .env defaults to.
 */
class StorefrontAuthMailSettingsTest extends TestCase
{
    use DatabaseTransactions;

    private int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        app(StorefrontSettingService::class)->save($this->businessId, [
            'turnstile' => ['site_key' => '', 'secret_key' => null],
        ]);
        Cache::flush();

        // .env mailer that would swallow the message if business settings were ignored.
        config(['mail.default' => 'log']);
        Business::whereKey($this->businessId)->update(['email_settings' => json_encode([
            'mail_driver' => 'array',
            'mail_host' => 'smtp.business.test',
            'mail_port' => 587,
            'mail_username' => 'shop@business.test',
            'mail_password' => 'secret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'shop@business.test',
            'mail_from_name' => 'Business Shop',
            'use_superadmin_settings' => 0,
        ])]);
    }

    public function test_forgot_password_email_uses_business_mail_settings(): void
    {
        $email = 'reset_mailer_'.uniqid().'@example.com';
        Contact::create([
            'business_id' => $this->businessId,
            'type' => 'customer',
            'name' => 'Reset Mailer',
            'email' => $email,
            'mobile' => '0100'.random_int(1000000, 9999999),
            'contact_id' => 'CO-RM'.random_int(100000, 999999),
            'created_by' => 1,
        ]);

        $this->postJson('/api/storefront/v1/auth/forgot-password', ['email' => $email])->assertOk();

        $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages, 'Reset email must be sent through the business mailer.');
        $sent = $messages->first()->getOriginalMessage();
        $this->assertSame($email, $sent->getTo()[0]->getAddress());
        $this->assertSame('shop@business.test', $sent->getFrom()[0]->getAddress());
    }
}
