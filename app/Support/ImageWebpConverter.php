<?php

namespace App\Support;

/**
 * Convert raster uploads to WebP when GD supports it.
 *
 * Skips SVG, ICO, already-WebP, animated GIF, and non-images.
 * Used by POS Util::uploadFile, storefront media library, and bulk artisan command.
 */
class ImageWebpConverter
{
    /** Extensions we never convert. */
    private const SKIP_EXTENSIONS = [
        'svg', 'svgz', 'ico', 'webp', 'pdf', 'mp4', 'webm', 'mov', 'avi',
        'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip', 'rar',
    ];

    /** Raster sources GD can typically decode via imagecreatefromstring. */
    private const CONVERTIBLE_EXTENSIONS = [
        'jpg', 'jpeg', 'jpe', 'png', 'gif', 'bmp', 'wbmp',
    ];

    public function enabled(): bool
    {
        return (bool) config('images.webp_on_upload', true);
    }

    public function quality(): int
    {
        $q = (int) config('images.webp_quality', 82);

        return max(1, min(100, $q));
    }

    public function isAvailable(): bool
    {
        return function_exists('imagewebp')
            && function_exists('imagecreatefromstring')
            && function_exists('imagecreatetruecolor');
    }

    /**
     * Whether this absolute path is a candidate for WebP conversion.
     */
    public function shouldConvert(string $absolutePath): bool
    {
        if (! $this->enabled() || ! $this->isAvailable()) {
            return false;
        }
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return false;
        }

        $ext = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
        if ($ext === '' || in_array($ext, self::SKIP_EXTENSIONS, true)) {
            return false;
        }
        if (! in_array($ext, self::CONVERTIBLE_EXTENSIONS, true)) {
            // Allow MIME-based detection when extension is missing/odd.
            $mime = $this->detectMime($absolutePath);
            if ($mime === null || ! str_starts_with($mime, 'image/')) {
                return false;
            }
            if (in_array($mime, ['image/svg+xml', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'], true)) {
                return false;
            }
        }

        if ($ext === 'gif' && $this->isAnimatedGif($absolutePath)) {
            return false;
        }

        return true;
    }

    /**
     * Convert a file under public/uploads/{dirName}/{fileName}.
     *
     * @return string|null New basename on success, null when skipped/failed
     */
    public function convertStoredUpload(string $dirName, string $fileName, bool $deleteOriginal = true): ?string
    {
        $dirName = trim(str_replace('\\', '/', $dirName), '/');
        $fileName = basename(str_replace('\\', '/', $fileName));
        if ($dirName === '' || $fileName === '' || str_contains($fileName, '..')) {
            return null;
        }

        $absolute = public_path('uploads/'.$dirName.'/'.$fileName);

        return $this->convertAbsolutePath($absolute, $deleteOriginal)['filename'] ?? null;
    }

    /**
     * Convert an absolute filesystem path to a sibling .webp file.
     *
     * @return array{path: string, filename: string, bytes: int}|null
     */
    public function convertAbsolutePath(string $absolutePath, bool $deleteOriginal = true): ?array
    {
        $absolutePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $absolutePath);
        if (! $this->shouldConvert($absolutePath)) {
            return null;
        }

        $dir = dirname($absolutePath);
        $base = pathinfo($absolutePath, PATHINFO_FILENAME);
        if ($base === '') {
            return null;
        }

        $targetName = $base.'.webp';
        $targetPath = $dir.DIRECTORY_SEPARATOR.$targetName;

        // Avoid clobbering an existing different file.
        if (is_file($targetPath) && realpath($targetPath) !== realpath($absolutePath)) {
            $targetName = $base.'_'.substr(sha1($absolutePath.microtime(true)), 0, 8).'.webp';
            $targetPath = $dir.DIRECTORY_SEPARATOR.$targetName;
        }

        $blob = @file_get_contents($absolutePath);
        if (! is_string($blob) || $blob === '') {
            return null;
        }

        $image = @imagecreatefromstring($blob);
        if ($image === false) {
            return null;
        }

        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($image);
        }
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $ok = @imagewebp($image, $targetPath, $this->quality());
        imagedestroy($image);

        if (! $ok || ! is_file($targetPath)) {
            @unlink($targetPath);

            return null;
        }

        @chmod($targetPath, 0644);
        $bytes = (int) filesize($targetPath);

        if ($deleteOriginal && realpath($absolutePath) !== realpath($targetPath)) {
            @unlink($absolutePath);
        }

        return [
            'path' => $targetPath,
            'filename' => $targetName,
            'bytes' => $bytes,
        ];
    }

    public function detectMime(string $absolutePath): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($absolutePath);
            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        }

        return null;
    }

    /**
     * Heuristic: multiple graphic-control extensions ⇒ animated GIF.
     */
    public function isAnimatedGif(string $absolutePath): bool
    {
        $fh = @fopen($absolutePath, 'rb');
        if ($fh === false) {
            return false;
        }

        $count = 0;
        $prev = '';
        while (! feof($fh)) {
            $chunk = fread($fh, 100_000);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data = $prev.$chunk;
            $count += substr_count($data, "\x00\x21\xF9\x04");
            if ($count >= 2) {
                fclose($fh);

                return true;
            }
            $prev = substr($data, -3);
        }
        fclose($fh);

        return false;
    }
}
