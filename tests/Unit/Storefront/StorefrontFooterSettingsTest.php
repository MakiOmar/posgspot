<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\SettingsApiService;
use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Footer menus in storefront settings (normalize + public locale overlay).
 */
class StorefrontFooterSettingsTest extends TestCase
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

    public function test_normalize_footer_caps_columns_and_links(): void
    {
        $columns = [];
        for ($c = 0; $c < 5; $c++) {
            $links = [];
            for ($l = 0; $l < 15; $l++) {
                $links[] = [
                    'label' => ['en' => "L{$c}-{$l}", 'ar' => ''],
                    'url' => "/p{$c}-{$l}",
                ];
            }
            $columns[] = [
                'title' => ['en' => "Col {$c}", 'ar' => ''],
                'links' => $links,
            ];
        }

        $normalized = $this->settings->normalizeFooter([
            'contact_title' => ['en' => 'Reach us', 'ar' => 'تواصل'],
            'columns' => $columns,
        ]);

        $this->assertSame('Reach us', $normalized['contact_title']['en']);
        $this->assertSame('تواصل', $normalized['contact_title']['ar']);
        $this->assertCount(3, $normalized['columns']);
        $this->assertCount(12, $normalized['columns'][0]['links']);
        $this->assertSame('L0-0', $normalized['columns'][0]['links'][0]['label']['en']);
    }

    public function test_public_settings_resolves_footer_locale(): void
    {
        $this->settings->save($this->businessId, [
            'footer' => [
                'contact_title' => ['en' => 'Contact Info', 'ar' => 'معلومات التواصل'],
                'columns' => [
                    [
                        'id' => 'col_test',
                        'title' => ['en' => 'Customer', 'ar' => 'العملاء'],
                        'links' => [
                            [
                                'id' => 'lnk_faq',
                                'label' => ['en' => 'Help Center', 'ar' => 'مركز المساعدة'],
                                'url' => '/faq',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        Cache::forget('storefront_settings_'.$this->businessId);

        $api = app(SettingsApiService::class);
        $en = $api->getPublicSettings($this->businessId, 'en');
        $ar = $api->getPublicSettings($this->businessId, 'ar');

        $this->assertSame('Contact Info', $en['footer']['contact_title']);
        $this->assertSame('Customer', $en['footer']['columns'][0]['title']);
        $this->assertSame('Help Center', $en['footer']['columns'][0]['links'][0]['label']);
        $this->assertSame('/faq', $en['footer']['columns'][0]['links'][0]['url']);

        $this->assertSame('معلومات التواصل', $ar['footer']['contact_title']);
        $this->assertSame('العملاء', $ar['footer']['columns'][0]['title']);
        $this->assertSame('مركز المساعدة', $ar['footer']['columns'][0]['links'][0]['label']);
    }

    public function test_defaults_include_three_footer_columns(): void
    {
        $defaults = $this->settings->defaults();
        $this->assertArrayHasKey('footer', $defaults);
        $this->assertCount(3, $defaults['footer']['columns']);
        $this->assertNotEmpty($defaults['footer']['columns'][0]['links']);
    }
}
