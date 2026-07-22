<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\StorefrontBundleService;
use App\Services\Storefront\StorefrontSettingService;
use App\StorefrontShippingClass;
use App\StorefrontShippingMethod;
use App\StorefrontShippingZone;
use App\StorefrontShippingZoneLocation;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use ZipArchive;

/**
 * Full storefront ZIP bundle export / import.
 */
class StorefrontBundleImportExportTest extends TestCase
{
    protected int $businessId = 1;

    /** @var list<int> */
    private array $createdZoneIds = [];

    /** @var list<int> */
    private array $createdClassIds = [];

    private ?string $tempMedia = null;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('storefront_settings_'.$this->businessId);
        Cache::forget('storefront_shipping_zones_'.$this->businessId);
    }

    protected function tearDown(): void
    {
        if ($this->createdZoneIds !== []) {
            StorefrontShippingZone::whereIn('id', $this->createdZoneIds)->delete();
        }
        if ($this->createdClassIds !== []) {
            StorefrontShippingClass::whereIn('id', $this->createdClassIds)->delete();
        }
        if ($this->tempMedia && is_file($this->tempMedia)) {
            @unlink($this->tempMedia);
        }
        Cache::forget('storefront_settings_'.$this->businessId);
        Cache::forget('storefront_shipping_zones_'.$this->businessId);
        parent::tearDown();
    }

    public function test_zip_export_includes_manifest_and_media(): void
    {
        $dir = public_path('uploads/storefront_homepage');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->tempMedia = $dir.DIRECTORY_SEPARATOR.'bundle_test_'.uniqid().'.txt';
        file_put_contents($this->tempMedia, 'media-fixture');

        app(StorefrontSettingService::class)->save($this->businessId, [
            'theme' => ['accent_color' => '#abcdef'],
            'payment_icons' => [
                [
                    'label' => 'Test',
                    'image' => basename($this->tempMedia),
                    'url' => '',
                ],
            ],
            // Point a homepage-style image key at the same file via banners.
            'banners' => [],
            'homepage_sections' => [
                [
                    'id' => 'sec_test',
                    'type' => 'promo_tiles',
                    'enabled' => true,
                    'settings' => [
                        'tiles' => [
                            ['image' => basename($this->tempMedia), 'url' => '', 'title' => ['en' => 'T', 'ar' => '']],
                        ],
                    ],
                ],
            ],
        ]);

        $bundle = app(StorefrontBundleService::class);
        $zipPath = $bundle->exportToTempZip($this->businessId);
        $this->assertFileExists($zipPath);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $manifestRaw = $zip->getFromName('manifest.json');
        $this->assertNotFalse($manifestRaw);
        $manifest = json_decode($manifestRaw, true);
        $this->assertSame(StorefrontBundleService::FORMAT, $manifest['format']);
        $this->assertSame('#abcdef', $manifest['settings']['theme']['accent_color']);
        $this->assertArrayHasKey('shipping', $manifest);
        $this->assertArrayHasKey('coupons', $manifest);
        $this->assertArrayHasKey('catalog_overlays', $manifest);
        $this->assertArrayHasKey('translations', $manifest);

        $mediaName = 'media/storefront_homepage/'.basename($this->tempMedia);
        $this->assertNotFalse($zip->locateName($mediaName));
        $zip->close();
        @unlink($zipPath);
    }

    public function test_shipping_round_trip_via_manifest_import(): void
    {
        $class = StorefrontShippingClass::create([
            'business_id' => $this->businessId,
            'name' => 'Bundle Class '.uniqid(),
            'slug' => 'bundle-class-'.uniqid(),
        ]);
        $this->createdClassIds[] = $class->id;

        $zone = StorefrontShippingZone::create([
            'business_id' => $this->businessId,
            'name' => 'Bundle Zone '.uniqid(),
            'priority' => 12,
            'is_enabled' => true,
            'is_catch_all' => false,
        ]);
        $this->createdZoneIds[] = $zone->id;

        StorefrontShippingZoneLocation::create([
            'zone_id' => $zone->id,
            'type' => 'state',
            'code' => 'C',
        ]);

        StorefrontShippingMethod::create([
            'zone_id' => $zone->id,
            'type' => StorefrontShippingMethod::TYPE_FLAT_RATE,
            'title' => 'Flat',
            'title_i18n' => ['en' => 'Flat', 'ar' => 'ثابت'],
            'settings' => [
                'cost' => 40,
                'class_costs' => [$class->id => 15],
            ],
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        $bundle = app(StorefrontBundleService::class);
        $zipPath = $bundle->exportToTempZip($this->businessId);

        // Wipe and re-import.
        StorefrontShippingZone::where('business_id', $this->businessId)->where('id', $zone->id)->delete();
        $this->createdZoneIds = [];

        $result = $bundle->importPath($this->businessId, $zipPath, 'zip');
        @unlink($zipPath);

        $this->assertContains('shipping', $result['sections']);
        $this->assertContains('settings', $result['sections']);

        $restored = StorefrontShippingZone::where('business_id', $this->businessId)
            ->where('name', $zone->name)
            ->with(['locations', 'methods'])
            ->first();
        $this->assertNotNull($restored);
        $this->createdZoneIds[] = $restored->id;

        $this->assertSame(1, $restored->locations->count());
        $this->assertSame('C', $restored->locations->first()->code);
        $this->assertSame(1, $restored->methods->count());
        $method = $restored->methods->first();
        $this->assertSame(40.0, (float) ($method->settings['cost'] ?? 0));

        $newClass = StorefrontShippingClass::where('business_id', $this->businessId)
            ->where('slug', $class->slug)
            ->first();
        $this->assertNotNull($newClass);
        $this->createdClassIds[] = $newClass->id;
        $this->assertEquals(15, $method->settings['class_costs'][$newClass->id] ?? null);
    }

    public function test_legacy_json_settings_still_imports(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sf_json_');
        file_put_contents($path, json_encode([
            'format' => StorefrontSettingService::EXPORT_FORMAT,
            'version' => 1,
            'settings' => [
                'cod_enabled' => false,
                'theme' => ['accent_color' => '#121212'],
            ],
        ]));

        $result = app(StorefrontBundleService::class)->importPath($this->businessId, $path, 'json');
        @unlink($path);

        $this->assertSame(['settings'], $result['sections']);
        $settings = app(StorefrontSettingService::class)->get($this->businessId);
        $this->assertFalse($settings['cod_enabled']);
        $this->assertSame('#121212', $settings['theme']['accent_color']);
    }
}
