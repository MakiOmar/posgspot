<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\StorefrontMediaLibraryService;
use App\StorefrontMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Per-business storefront media library (checksum dedupe).
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
        // Fake createWithContent may set octet-stream; force svg extension path.
        $library = app(StorefrontMediaLibraryService::class);

        $first = $library->storeUploadedFile($this->businessId, $file1, null);
        $this->assertTrue($first['created']);
        $this->assertSame('svg', $first['media']->kind);
        $this->assertStringStartsWith('storefront_library/'.$this->businessId.'/', $first['media']->path);

        $file2 = UploadedFile::fake()->createWithContent('badge-copy.svg', $svg);
        $second = $library->storeUploadedFile($this->businessId, $file2, null);
        $this->assertFalse($second['created']);
        $this->assertSame($first['media']->id, $second['media']->id);

        $list = $library->list($this->businessId, 'svg');
        $this->assertSame(1, $list['meta']['total']);
    }

    public function test_store_svg_markup_and_delete(): void
    {
        $library = app(StorefrontMediaLibraryService::class);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>';
        $result = $library->storeSvgMarkup($this->businessId, $svg, null, 'pasted.svg');
        $this->assertTrue($result['created']);
        $path = public_path('uploads/'.$result['media']->path);
        $this->assertFileExists($path);

        $this->assertTrue($library->delete($this->businessId, (int) $result['media']->id));
        $this->assertNull($library->findForBusiness($this->businessId, (int) $result['media']->id));
        // File retained so checksum restore can revive the row.
        $this->assertFileExists($path);

        $again = $library->storeSvgMarkup($this->businessId, $svg, null, 'pasted.svg');
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
