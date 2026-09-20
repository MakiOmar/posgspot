<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\SettingsApiService;
use App\Services\Storefront\StorefrontHtmlSanitizer;
use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Digital PDP settings: WhatsApp digits, FAQ caps, public payload.
 */
class StorefrontDigitalPdpSettingsTest extends TestCase
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

    public function test_normalize_whatsapp_digits_rejects_invalid(): void
    {
        $this->assertSame('', $this->settings->normalizeWhatsAppDigits('javascript:alert(1)'));
        $this->assertSame('', $this->settings->normalizeWhatsAppDigits('abc'));
        $this->assertSame('', $this->settings->normalizeWhatsAppDigits('123'));
        $this->assertSame('201012345678', $this->settings->normalizeWhatsAppDigits('+20 101 234 5678'));
    }

    public function test_normalize_pdp_faqs_caps_and_strips_html(): void
    {
        $rows = [];
        for ($i = 0; $i < 25; $i++) {
            $rows[] = [
                'question' => ['en' => "<b>Q{$i}</b>", 'ar' => ''],
                'answer' => ['en' => "<script>x</script>Answer {$i}", 'ar' => ''],
            ];
        }

        $normalized = $this->settings->normalizeDigitalPdpFaqs($rows);

        $this->assertCount(20, $normalized);
        $this->assertSame('Q0', $normalized[0]['question']['en']);
        $this->assertStringNotContainsString('<script>', $normalized[0]['answer']['en']);
        $this->assertStringContainsString('Answer 0', $normalized[0]['answer']['en']);
    }

    public function test_empty_faqs_seed_defaults(): void
    {
        $normalized = $this->settings->normalizeDigitalPdpFaqs([]);
        $this->assertGreaterThanOrEqual(6, count($normalized));
        $this->assertSame('What are Digital Games?', $normalized[0]['question']['en']);
    }

    public function test_public_settings_exposes_ask_whatsapp_and_locale_faqs(): void
    {
        $this->settings->save($this->businessId, [
            'digital' => [
                'enabled' => true,
                'ask_whatsapp' => '+20 100 111 2233',
                'pdp_faqs' => [
                    [
                        'question' => ['en' => 'EN Q', 'ar' => 'سؤال'],
                        'answer' => ['en' => 'EN A', 'ar' => 'جواب'],
                    ],
                ],
            ],
            'contact' => [
                'whatsapp' => '201099988877',
            ],
        ]);

        $api = app(SettingsApiService::class);
        $en = $api->getPublicSettings($this->businessId, 'en');
        $ar = $api->getPublicSettings($this->businessId, 'ar');

        $this->assertSame('201001112233', $en['digital']['ask_whatsapp']);
        $this->assertSame('EN Q', $en['digital']['pdp_faqs'][0]['question']);
        $this->assertSame('EN A', $en['digital']['pdp_faqs'][0]['answer']);
        $this->assertSame('سؤال', $ar['digital']['pdp_faqs'][0]['question']);
        $this->assertSame('جواب', $ar['digital']['pdp_faqs'][0]['answer']);
    }

    public function test_ask_whatsapp_falls_back_to_contact(): void
    {
        $this->settings->save($this->businessId, [
            'digital' => [
                'enabled' => true,
                'ask_whatsapp' => '',
                'pdp_faqs' => $this->settings->defaultDigitalPdpFaqs(),
            ],
            'contact' => [
                'whatsapp' => '201055566677',
            ],
        ]);

        $api = app(SettingsApiService::class);
        $payload = $api->getPublicSettings($this->businessId, 'en');

        $this->assertSame('201055566677', $payload['digital']['ask_whatsapp']);
    }

    public function test_html_sanitizer_strips_scripts_from_description(): void
    {
        $sanitizer = app(StorefrontHtmlSanitizer::class);
        $clean = $sanitizer->sanitize('<p>Hello</p><script>alert(1)</script><a href="javascript:x">x</a>');

        $this->assertStringContainsString('<p>Hello</p>', (string) $clean);
        $this->assertStringNotContainsString('<script>', (string) $clean);
        $this->assertStringNotContainsString('javascript:', (string) $clean);
    }
}
