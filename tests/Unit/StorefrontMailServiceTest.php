<?php

namespace Tests\Unit;

use App\Business;
use App\Services\Storefront\StorefrontMailService;
use App\System;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StorefrontMailServiceTest extends TestCase
{
    public function test_resolve_from_never_returns_empty_address(): void
    {
        Config::set('mail.from.address', '');
        Config::set('mail.from.name', '');
        Config::set('mail.mailers.smtp.username', '');
        Config::set('app.url', 'https://posstaging.gamesspoteg.com');

        $from = app(StorefrontMailService::class)->resolveFrom(1);

        $this->assertNotSame('', $from['address']);
        $this->assertNotSame('', $from['name']);
        $this->assertStringContainsString('@', $from['address']);
    }

    public function test_apply_for_business_keeps_mailgun_when_using_superadmin_settings(): void
    {
        Config::set('mail.default', 'mailgun');
        Config::set('mail.mailers.smtp.host', 'smtp.env.test');
        Config::set('mail.from.address', 'system@example.com');
        Config::set('mail.from.name', 'System');

        System::updateOrCreate(
            ['key' => 'allow_email_settings_to_businesses'],
            ['value' => '1']
        );

        $business = Business::query()->find(1);
        if (! $business) {
            $this->markTestSkipped('Business id 1 required for storefront mail integration test.');
        }

        $originalSettings = $business->email_settings;

        try {
            $settings = array_merge($originalSettings ?? [], [
                'use_superadmin_settings' => 1,
                'mail_host' => 'smtp.should-not-apply.test',
                'mail_driver' => 'smtp',
                'mail_from_address' => 'shop@example.com',
                'mail_from_name' => 'Shop',
            ]);
            $business->email_settings = $settings;
            $business->save();

            app(StorefrontMailService::class)->applyForBusiness(1);

            $this->assertSame('mailgun', config('mail.default'));
            $this->assertSame('smtp.env.test', config('mail.mailers.smtp.host'));
            $this->assertSame('shop@example.com', config('mail.from.address'));
            $this->assertSame('Shop', config('mail.from.name'));
        } finally {
            $business->email_settings = $originalSettings;
            $business->save();
        }
    }
}
