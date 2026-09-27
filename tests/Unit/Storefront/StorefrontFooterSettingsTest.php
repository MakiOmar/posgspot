<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\SettingsApiService;
use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Footer menus in storefront settings (normalize + public locale overlay).
 */
class StorefrontFooterSettingsTest extends TestCase
{
    use DatabaseTransactions;

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
        for ($c = 0; $c < 6; $c++) {
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
        $this->assertCount(StorefrontSettingService::FOOTER_MAX_COLUMNS, $normalized['columns']);
        $this->assertCount(StorefrontSettingService::FOOTER_MAX_LINKS, $normalized['columns'][0]['links']);
        $this->assertSame('L0-0', $normalized['columns'][0]['links'][0]['label']['en']);
    }

    public function test_normalize_footer_drops_duplicate_urls_across_columns(): void
    {
        $normalized = $this->settings->normalizeFooter([
            'columns' => [
                [
                    'title' => ['en' => 'A', 'ar' => ''],
                    'links' => [
                        ['label' => ['en' => 'FAQs', 'ar' => ''], 'url' => '/faqs'],
                        ['label' => ['en' => 'FAQ again', 'ar' => ''], 'url' => '/FAQs/'],
                    ],
                ],
                [
                    'title' => ['en' => 'B', 'ar' => ''],
                    'links' => [
                        ['label' => ['en' => 'FAQ dup', 'ar' => ''], 'url' => '/faqs'],
                        ['label' => ['en' => 'Contact', 'ar' => ''], 'url' => '/contact'],
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $normalized['columns'][0]['links']);
        $this->assertSame('FAQs', $normalized['columns'][0]['links'][0]['label']['en']);
        $this->assertCount(1, $normalized['columns'][1]['links']);
        $this->assertSame('/contact', $normalized['columns'][1]['links'][0]['url']);
    }

    public function test_disabled_feature_links_are_stripped_from_footer(): void
    {
        config([
            'storefront.custom_bundle.enabled' => false,
            'storefront.sell_to_us.enabled' => false,
        ]);

        $footer = $this->settings->stripDisabledFeatureFooterLinks([
            'columns' => [
                [
                    'id' => 'col_shop',
                    'title' => ['en' => 'Shop', 'ar' => ''],
                    'links' => [
                        ['id' => 'a', 'label' => ['en' => 'Bundle', 'ar' => ''], 'url' => '/custom-bundle'],
                        ['id' => 'b', 'label' => ['en' => 'Sell', 'ar' => ''], 'url' => '/sell-to-us'],
                        ['id' => 'c', 'label' => ['en' => 'Search', 'ar' => ''], 'url' => '/search'],
                    ],
                ],
            ],
        ]);

        $urls = array_column($footer['columns'][0]['links'], 'url');
        $this->assertSame(['/search'], $urls);
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

    public function test_defaults_include_four_footer_columns(): void
    {
        $defaults = $this->settings->defaults();
        $this->assertArrayHasKey('footer', $defaults);
        $this->assertSame(
            ['col_shop', 'col_account', 'col_help', 'col_company'],
            array_column($defaults['footer']['columns'], 'id')
        );
        foreach ($defaults['footer']['columns'] as $column) {
            $this->assertNotEmpty($column['links']);
        }
    }

    public function test_save_footer_keeps_other_settings(): void
    {
        $this->settings->save($this->businessId, ['cod_enabled' => false]);
        Cache::forget('storefront_settings_'.$this->businessId);
        $before = $this->settings->get($this->businessId);

        $this->settings->saveFooter($this->businessId, $this->settings->defaultFooter());

        $after = $this->settings->get($this->businessId);
        $this->assertFalse($after['cod_enabled']);
        $this->assertSame(
            ['col_shop', 'col_account', 'col_help', 'col_company'],
            array_column($after['footer']['columns'], 'id')
        );
        unset($before['footer'], $after['footer']);
        $this->assertSame($before, $after);
    }

    public function test_reset_footer_command_reseeds_defaults(): void
    {
        $this->settings->save($this->businessId, [
            'footer' => [
                'columns' => [
                    [
                        'id' => 'col_old',
                        'title' => ['en' => 'Old', 'ar' => ''],
                        'links' => [['label' => ['en' => 'X', 'ar' => ''], 'url' => '/x']],
                    ],
                ],
            ],
        ]);

        $this->artisan('storefront:reset-footer', [
            '--business_id' => $this->businessId,
            '--force' => true,
        ])->assertSuccessful();

        Cache::forget('storefront_settings_'.$this->businessId);
        $ids = array_column($this->settings->get($this->businessId)['footer']['columns'], 'id');
        $this->assertSame(['col_shop', 'col_account', 'col_help', 'col_company'], $ids);
    }
}
