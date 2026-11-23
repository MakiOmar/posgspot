<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class TurnstileSecretUnwrapTest extends TestCase
{
    public function test_decrypt_unwraps_double_encrypted_turnstile_secret(): void
    {
        $plain = '0x4AAAAAATestTurnstileSecretKey99';
        $once = Crypt::encryptString($plain);
        $twice = Crypt::encryptString($once);

        $service = app(StorefrontSettingService::class);

        $this->assertSame(
            $plain,
            $service->decryptTurnstileSecretKey(['turnstile' => ['secret_key' => $twice]])
        );
        $this->assertSame(
            $plain,
            $service->decryptTurnstileSecretKey(['turnstile' => ['secret_key' => $once]])
        );
        $this->assertSame(
            $plain,
            $service->decryptTurnstileSecretKey(['turnstile' => ['secret_key' => $plain]])
        );
    }

    public function test_save_stores_a_single_encryption_layer_for_turnstile_secret(): void
    {
        $plain = '0x4AAAAAATestTurnstileSecretKey88';
        $service = app(StorefrontSettingService::class);
        $businessId = 1;

        $service->save($businessId, [
            'turnstile' => [
                'site_key' => '0x4AAAAAATestSite',
                'secret_key' => Crypt::encryptString($plain), // already encrypted once
            ],
        ]);

        $settings = $service->get($businessId);
        $this->assertSame($plain, $service->decryptTurnstileSecretKey($settings));

        $raw = (new \ReflectionMethod($service, 'getRaw'))->invoke($service, $businessId);
        $stored = (string) ($raw['turnstile']['secret_key'] ?? '');
        $this->assertTrue(str_starts_with($stored, 'eyJpdiI'));
        $this->assertSame($plain, Crypt::decryptString($stored));
    }
}
