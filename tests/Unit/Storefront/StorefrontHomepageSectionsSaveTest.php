<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Homepage save must not Eloquent-cast multi‑MB inline SVG blobs.
 */
class StorefrontHomepageSectionsSaveTest extends TestCase
{
    protected int $businessId = 1;

    public function test_replace_json_key_value_swaps_array(): void
    {
        $svc = app(StorefrontSettingService::class);
        $json = '{"cod_enabled":true,"homepage_sections":[{"id":"old","svg_markup":"HUGE"}],"theme":{"accent_color":"#00d4aa"}}';
        $out = $svc->replaceJsonKeyValue($json, 'homepage_sections', '[{"id":"new"}]');
        $this->assertNotNull($out);
        $decoded = json_decode($out, true);
        $this->assertSame('new', $decoded['homepage_sections'][0]['id']);
        $this->assertTrue($decoded['cod_enabled']);
        $this->assertSame('#00d4aa', $decoded['theme']['accent_color']);
    }

    public function test_strip_inline_svg_keys_from_json_string(): void
    {
        $svc = app(StorefrontSettingService::class);
        $json = '{"items":[{"title":"A","svg_markup":"<svg>big</svg>","svg_markup_b64":"YWJj","icon_kind":"svg"}]}';
        $out = $svc->stripInlineSvgKeysFromJsonString($json);
        $this->assertNotNull($out);
        $decoded = json_decode($out, true);
        $this->assertSame('A', $decoded['items'][0]['title']);
        $this->assertArrayNotHasKey('svg_markup', $decoded['items'][0]);
        $this->assertArrayNotHasKey('svg_markup_b64', $decoded['items'][0]);
    }

    public function test_save_homepage_sections_replaces_oversized_blob_without_oom_path(): void
    {
        $poison = str_repeat('A', 1_200_000);
        $blob = json_encode([
            'cod_enabled' => true,
            'selling_location_ids' => [1],
            'homepage_sections' => [
                [
                    'id' => 'sec_trust',
                    'type' => 'trust_badges',
                    'enabled' => true,
                    'settings' => [
                        'items' => [
                            [
                                'id' => 'badge_1',
                                'icon_kind' => 'svg',
                                'svg_markup' => $poison,
                                'title' => ['en' => 'Old', 'ar' => ''],
                                'description' => ['en' => '', 'ar' => ''],
                            ],
                        ],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $this->assertGreaterThan(1_000_000, strlen($blob));

        DB::table('storefront_settings')->updateOrInsert(
            ['business_id' => $this->businessId],
            [
                'value' => $blob,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $svc = app(StorefrontSettingService::class);
        $normalized = $svc->saveHomepageSections($this->businessId, [
            [
                'id' => 'sec_trust',
                'type' => 'trust_badges',
                'enabled' => true,
                'settings' => [
                    'items' => [
                        [
                            'id' => 'badge_1',
                            'icon_kind' => 'image',
                            'url' => 'https://example.com/icon.png',
                            'title' => ['en' => 'New', 'ar' => ''],
                            'description' => ['en' => 'ok', 'ar' => ''],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('trust_badges', $normalized[0]['type']);
        $stored = DB::table('storefront_settings')->where('business_id', $this->businessId)->value('value');
        $storedStr = is_string($stored) ? $stored : json_encode($stored);
        $this->assertLessThan(100_000, strlen((string) $storedStr));
        $this->assertStringNotContainsString($poison, (string) $storedStr);
        $this->assertStringContainsString('https://example.com/icon.png', (string) $storedStr);
    }

    public function test_scrub_clears_oversized_strings_and_resets_homepage(): void
    {
        $poison = str_repeat('B', 200_000);
        DB::table('storefront_settings')->updateOrInsert(
            ['business_id' => $this->businessId],
            [
                'value' => json_encode([
                    'cod_enabled' => true,
                    'homepage_sections' => [
                        [
                            'id' => 'sec_trust',
                            'type' => 'trust_badges',
                            'enabled' => true,
                            'settings' => [
                                'items' => [
                                    [
                                        'id' => 'badge_1',
                                        'url' => $poison,
                                        'title' => ['en' => 'X', 'ar' => ''],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $result = app(StorefrontSettingService::class)->scrubInlineSvgFromStoredSettings($this->businessId);
        $this->assertLessThan(100_000, $result['after_bytes']);
        $this->assertNotEmpty($result['cleared_strings']);
        $stored = DB::table('storefront_settings')->where('business_id', $this->businessId)->value('value');
        $storedStr = is_string($stored) ? $stored : json_encode($stored);
        $this->assertStringNotContainsString($poison, (string) $storedStr);
    }

    public function test_measure_and_lean_top_level_json_keys(): void
    {
        $svc = app(StorefrontSettingService::class);
        $poison = str_repeat('Z', 120_000);
        $json = json_encode([
            'cod_enabled' => true,
            'mystery_dump' => $poison,
            'homepage_sections' => [['id' => 'a']],
        ], JSON_UNESCAPED_UNICODE);
        $this->assertNotFalse($json);

        $sizes = $svc->measureTopLevelJsonKeySizes($json);
        $this->assertGreaterThan(100_000, $sizes['mystery_dump']);
        $this->assertLessThan(5000, $sizes['homepage_sections']);

        $lean = $svc->leanOversizedTopLevelJsonValues($json, 50_000);
        $this->assertNotNull($lean);
        $decoded = json_decode($lean, true);
        $this->assertTrue($decoded['cod_enabled']);
        $this->assertSame('', $decoded['mystery_dump']);
        $this->assertSame('a', $decoded['homepage_sections'][0]['id']);
        $this->assertLessThan(5000, strlen($lean));
    }
}
