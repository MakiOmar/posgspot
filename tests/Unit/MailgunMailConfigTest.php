<?php

namespace Tests\Unit;

use App\Utils\NotificationUtil;
use App\Utils\Util;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * System-wide Mailgun API mail configuration helpers.
 */
class MailgunMailConfigTest extends TestCase
{
    public function test_is_mail_configured_true_for_mailgun(): void
    {
        Config::set('mail.default', 'mailgun');
        Config::set('services.mailgun.domain', 'mg.example.com');
        Config::set('services.mailgun.secret', 'key-test');
        Config::set('mail.from.address', 'noreply@example.com');

        $this->assertTrue((new Util())->IsMailConfigured());
    }

    public function test_is_mail_configured_false_when_mailgun_secret_missing(): void
    {
        Config::set('mail.default', 'mailgun');
        Config::set('services.mailgun.domain', 'mg.example.com');
        Config::set('services.mailgun.secret', '');
        Config::set('mail.from.address', 'noreply@example.com');

        $this->assertFalse((new Util())->IsMailConfigured());
    }

    public function test_is_mail_configured_true_for_smtp(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.example.com');
        Config::set('mail.mailers.smtp.port', 587);
        Config::set('mail.mailers.smtp.username', 'user@example.com');
        Config::set('mail.mailers.smtp.password', 'secret');
        Config::set('mail.from.address', 'noreply@example.com');

        $this->assertTrue((new Util())->IsMailConfigured());
    }

    public function test_configure_email_sets_mailgun_default_without_smtp_host(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.should-stay.test');

        $util = new NotificationUtil();
        $util->configureEmail([
            'email_settings' => [
                'mail_driver' => 'mailgun',
                'mail_host' => 'smtp.ignored.test',
                'mail_from_address' => 'shop@example.com',
                'mail_from_name' => 'Shop',
            ],
        ], false);

        $this->assertSame('mailgun', config('mail.default'));
        $this->assertSame('smtp.should-stay.test', config('mail.mailers.smtp.host'));
        $this->assertSame('shop@example.com', config('mail.from.address'));
        $this->assertSame('Shop', config('mail.from.name'));
    }

    public function test_configure_email_applies_smtp_mailer_keys(): void
    {
        $util = new NotificationUtil();
        $util->configureEmail([
            'email_settings' => [
                'mail_driver' => 'smtp',
                'mail_host' => 'smtp.business.test',
                'mail_port' => 587,
                'mail_username' => 'biz@example.com',
                'mail_password' => 'secret',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'biz@example.com',
                'mail_from_name' => 'Biz',
            ],
        ], false);

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.business.test', config('mail.mailers.smtp.host'));
        $this->assertSame(587, (int) config('mail.mailers.smtp.port'));
        $this->assertSame('biz@example.com', config('mail.mailers.smtp.username'));
        $this->assertSame('biz@example.com', config('mail.from.address'));
    }

    public function test_configure_email_superadmin_keeps_mailgun_transport(): void
    {
        Config::set('mail.default', 'mailgun');
        Config::set('mail.from.address', 'system@example.com');
        Config::set('mail.from.name', 'System');
        Config::set('mail.mailers.smtp.host', 'smtp.should-not-apply.test');

        \App\System::updateOrCreate(
            ['key' => 'allow_email_settings_to_businesses'],
            ['value' => '1']
        );

        $util = new NotificationUtil();
        $util->configureEmail([
            'email_settings' => [
                'use_superadmin_settings' => 1,
                'mail_host' => 'smtp.business-override.test',
                'mail_from_address' => '',
                'mail_from_name' => 'Business Name',
            ],
        ], true);

        $this->assertSame('mailgun', config('mail.default'));
        $this->assertSame('smtp.should-not-apply.test', config('mail.mailers.smtp.host'));
        $this->assertSame('system@example.com', config('mail.from.address'));
        $this->assertSame('Business Name', config('mail.from.name'));
    }
}
