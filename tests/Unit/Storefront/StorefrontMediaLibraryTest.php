<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\StorefrontMediaLibraryService;
use App\StorefrontMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Per-business storefront media library (checksum dedupe; no inline SVG processing).
 */
class StorefrontMediaLibraryTest extends TestCase
{
    protected int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('storefront_media')) {
            $this->markTestSkipped('storefront_media table missing — run migrations.');
        }
        StorefrontMedia::withTrashed()->where('business_id', $this->businessId)->forceDelete();
    }

    public function test_upload_dedupes_by_checksum(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>';
        $file1 = UploadedFile::fake()->createWithContent('badge.svg', $svg);
        $library = app(StorefrontMediaLibraryService::class);

        $first = $library->storeUploadedFile($this->businessId, $file1, null);
        $this->assertTrue($first['created']);
        $this->assertSame('svg', $first['media']->kind);
        $this->assertStringStartsWith('storefront_library/'.$this->businessId.'/', $first['media']->path);
        $this->assertArrayNotHasKey('svg_markup', $first);

        $file2 = UploadedFile::fake()->createWithContent('badge-copy.svg', $svg);
        $second = $library->storeUploadedFile($this->businessId, $file2, null);
        $this->assertFalse($second['created']);
        $this->assertSame($first['media']->id, $second['media']->id);

        $list = $library->list($this->businessId, 'svg');
        $this->assertSame(1, $list['meta']['total']);
    }

    public function test_delete_soft_hides_then_reupload_restores(): void
    {
        $library = app(StorefrontMediaLibraryService::class);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>';
        $file = UploadedFile::fake()->createWithContent('pasted.svg', $svg);
        $result = $library->storeUploadedFile($this->businessId, $file, null);
        $this->assertTrue($result['created']);
        $path = public_path('uploads/'.$result['media']->path);
        $this->assertFileExists($path);

        $this->assertTrue($library->delete($this->businessId, (int) $result['media']->id));
        $this->assertNull($library->findForBusiness($this->businessId, (int) $result['media']->id));
        $this->assertFileExists($path);

        $again = $library->storeUploadedFile(
            $this->businessId,
            UploadedFile::fake()->createWithContent('pasted.svg', $svg),
            null
        );
        $this->assertFalse($again['created']);
        $this->assertSame($result['media']->id, $again['media']->id);
    }

    public function test_media_public_url_resolves_library_paths(): void
    {
        $svc = app(\App\Services\Storefront\Homepage\HomepageSectionService::class);
        $url = $svc->mediaPublicUrl('storefront_library/1/demo.svg', null);
        $this->assertNotNull($url);
        $this->assertStringContainsString('uploads/storefront_library/1/demo.svg', $url);

        $legacy = $svc->mediaPublicUrl('old-file.png', null);
        $this->assertStringContainsString('uploads/storefront_homepage/old-file.png', $legacy);
    }
}
