<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\StorefrontSettingService;
use App\StorefrontSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Storefront settings JSON export / import (secrets redacted, merge via save).
 */
class StorefrontSettingsImportExportTest extends TestCase
{
    protected int $businessId = 1;

    private StorefrontSettingService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settings = app(StorefrontSettingService::class);
        Cache::forget('storefront_settings_'.$this->businessId);
    }

    protected function tearDown(): void
    {
        Cache::forget('storefront_settings_'.$this->businessId);
        parent::tearDown();
    }

    public function test_export_envelope_redacts_secrets(): void
    {
        $this->settings->save($this->businessId, [
            'theme' => ['accent_color' => '#112233'],
            'turnstile' => [
                'site_key' => 'site-public',
                'secret_key' => 'super-secret-turnstile',
            ],
            'gateway' => [
                'provider' => 'fawry',
                'enabled' => true,
                'fawry' => [
                    'merchant_code' => 'M123',
                    'security_key' => 'fawry-secret',
                    'staging' => true,
                ],
            ],
        ]);

        $envelope = $this->settings->exportEnvelope($this->businessId);

        $this->assertSame(StorefrontSettingService::EXPORT_FORMAT, $envelope['format']);
        $this->assertSame(StorefrontSettingService::EXPORT_VERSION, $envelope['version']);
        $this->assertSame('#112233', $envelope['settings']['theme']['accent_color']);
        $this->assertSame('site-public', $envelope['settings']['turnstile']['site_key']);
        $this->assertNull($envelope['settings']['turnstile']['secret_key']);
        $this->assertNull($envelope['settings']['gateway']['fawry']['security_key']);
        $this->assertSame('M123', $envelope['settings']['gateway']['fawry']['merchant_code']);
    }

    public function test_import_merges_settings_and_preserves_existing_secrets(): void
    {
        $this->settings->save($this->businessId, [
            'theme' => ['accent_color' => '#aaaaaa'],
            'turnstile' => [
                'site_key' => 'old-site',
                'secret_key' => 'keep-me-secret',
            ],
            'announcement' => [
                'enabled' => false,
                'message' => ['en' => 'Old', 'ar' => ''],
                'link' => '',
            ],
        ]);

        $before = StorefrontSetting::where('business_id', $this->businessId)->firstOrFail();
        $encryptedSecret = $before->value['turnstile']['secret_key'] ?? null;
        $this->assertNotEmpty($encryptedSecret);
        $this->assertSame('keep-me-secret', Crypt::decryptString($encryptedSecret));

        $this->settings->importFromPayload($this->businessId, [
            'format' => StorefrontSettingService::EXPORT_FORMAT,
            'version' => 1,
            'settings' => [
                'theme' => ['accent_color' => '#00ffaa'],
                'announcement' => [
                    'enabled' => true,
                    'message' => ['en' => 'Imported', 'ar' => 'مستورد'],
                    'link' => '/sale',
                ],
                'turnstile' => [
                    'site_key' => 'new-site',
                    'secret_key' => null,
                ],
            ],
        ]);

        $after = $this->settings->get($this->businessId);
        $this->assertSame('#00ffaa', $after['theme']['accent_color']);
        $this->assertTrue($after['announcement']['enabled']);
        $this->assertSame('Imported', $after['announcement']['message']['en']);
        $this->assertSame('new-site', $after['turnstile']['site_key']);
        $this->assertSame('keep-me-secret', $this->settings->decryptTurnstileSecretKey($after));
    }

    public function test_import_accepts_raw_settings_object(): void
    {
        $this->settings->importFromPayload($this->businessId, [
            'cod_enabled' => false,
            'maintenance_mode' => true,
        ]);

        $after = $this->settings->get($this->businessId);
        $this->assertFalse($after['cod_enabled']);
        $this->assertTrue($after['maintenance_mode']);
    }

    public function test_import_rejects_unknown_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unrecognized import format');

        $this->settings->importFromPayload($this->businessId, [
            'format' => 'something_else',
            'version' => 1,
            'settings' => ['cod_enabled' => true],
        ]);
    }

    public function test_import_rejects_empty_payload(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->settings->importFromPayload($this->businessId, [
            'foo' => 'bar',
        ]);
    }
}
