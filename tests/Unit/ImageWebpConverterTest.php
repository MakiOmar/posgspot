<?php

namespace Tests\Unit;

use App\Support\ImageWebpConverter;
use Tests\TestCase;

class ImageWebpConverterTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'webp_conv_'.uniqid('', true);
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    public function test_converts_png_to_webp_when_gd_supports_it(): void
    {
        $converter = app(ImageWebpConverter::class);
        if (! $converter->isAvailable()) {
            $this->markTestSkipped('GD imagewebp() not available');
        }

        $png = $this->tmpDir.DIRECTORY_SEPARATOR.'sample.png';
        $this->assertTrue($this->writeTinyPng($png));

        $result = $converter->convertAbsolutePath($png, true);
        $this->assertNotNull($result);
        $this->assertSame('sample.webp', $result['filename']);
        $this->assertFileExists($result['path']);
        $this->assertFileDoesNotExist($png);
        $this->assertGreaterThan(0, $result['bytes']);
    }

    public function test_skips_existing_webp(): void
    {
        $converter = app(ImageWebpConverter::class);
        if (! $converter->isAvailable()) {
            $this->markTestSkipped('GD imagewebp() not available');
        }

        $png = $this->tmpDir.DIRECTORY_SEPARATOR.'once.png';
        $this->assertTrue($this->writeTinyPng($png));
        $first = $converter->convertAbsolutePath($png, true);
        $this->assertNotNull($first);

        $this->assertFalse($converter->shouldConvert($first['path']));
        $this->assertNull($converter->convertAbsolutePath($first['path'], true));
    }

    public function test_skips_svg_extension(): void
    {
        $converter = app(ImageWebpConverter::class);
        $svg = $this->tmpDir.DIRECTORY_SEPARATOR.'icon.svg';
        file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $this->assertFalse($converter->shouldConvert($svg));
        $this->assertNull($converter->convertAbsolutePath($svg, true));
        $this->assertFileExists($svg);
    }

    private function writeTinyPng(string $path): bool
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagepng')) {
            return false;
        }
        $im = imagecreatetruecolor(8, 8);
        $color = imagecolorallocate($im, 0, 120, 255);
        imagefilledrectangle($im, 0, 0, 7, 7, $color);
        $ok = imagepng($im, $path);
        imagedestroy($im);

        return (bool) $ok;
    }
}
