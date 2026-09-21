<?php

namespace App\Services\Storefront;

use App\StorefrontMedia;
use App\Support\ImageWebpConverter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-convert existing storefront setting / library raster files to WebP and rewrite DB paths.
 */
class StorefrontImageWebpBulkService
{
    /** Flat upload dirs used by storefront Appearance / homepage (filenames only in settings). */
    public const FLAT_DIRS = [
        'storefront_logo',
        'storefront_favicon',
        'storefront_payment_icons',
        'storefront_about_team',
        'storefront_banners',
        'storefront_homepage',
    ];

    public function __construct(
        private ImageWebpConverter $webp
    ) {
    }

    /**
     * @return array{
     *   business_id: int,
     *   converted: int,
     *   skipped: int,
     *   failed: int,
     *   rewritten_settings: int,
     *   rewritten_media: int,
     *   dry_run: bool,
     *   details: list<string>
     * }
     */
    public function convertBusiness(int $businessId, bool $dryRun = false, bool $keepOriginals = false): array
    {
        $map = []; // old relative path or basename => new basename or relative path
        $details = [];
        $converted = 0;
        $skipped = 0;
        $failed = 0;

        foreach (self::FLAT_DIRS as $dir) {
            $result = $this->convertDirectory($dir, $dryRun, $keepOriginals, $map, $details);
            $converted += $result['converted'];
            $skipped += $result['skipped'];
            $failed += $result['failed'];
        }

        $libDir = StorefrontMediaLibraryService::DIR.'/'.$businessId;
        $libResult = $this->convertDirectory($libDir, $dryRun, $keepOriginals, $map, $details, true);
        $converted += $libResult['converted'];
        $skipped += $libResult['skipped'];
        $failed += $libResult['failed'];

        $rewrittenSettings = 0;
        $rewrittenMedia = 0;

        if ($map !== []) {
            if (! $dryRun) {
                $rewrittenSettings = $this->rewriteSettingsPaths($businessId, $map);
                $rewrittenMedia = $this->rewriteMediaRows($businessId, $map);
            } else {
                $rewrittenSettings = $this->countSettingsRewrites($businessId, $map);
                $rewrittenMedia = $this->countMediaRewrites($businessId, $map);
            }
        }

        return [
            'business_id' => $businessId,
            'converted' => $converted,
            'skipped' => $skipped,
            'failed' => $failed,
            'rewritten_settings' => $rewrittenSettings,
            'rewritten_media' => $rewrittenMedia,
            'dry_run' => $dryRun,
            'details' => $details,
        ];
    }

    /**
     * @param  array<string, string>  $map
     * @param  list<string>  $details
     * @return array{converted: int, skipped: int, failed: int}
     */
    private function convertDirectory(
        string $dir,
        bool $dryRun,
        bool $keepOriginals,
        array &$map,
        array &$details,
        bool $storeRelativeKeys = false
    ): array {
        $converted = 0;
        $skipped = 0;
        $failed = 0;

        $absDir = public_path('uploads/'.trim($dir, '/'));
        if (! is_dir($absDir)) {
            return compact('converted', 'skipped', 'failed');
        }

        foreach (scandir($absDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $abs = $absDir.DIRECTORY_SEPARATOR.$entry;
            if (! is_file($abs)) {
                continue;
            }

            if (! $this->webp->shouldConvert($abs)) {
                $skipped++;
                continue;
            }

            $oldName = $entry;
            $oldKey = $storeRelativeKeys ? trim($dir, '/').'/'.$oldName : $oldName;

            if ($dryRun) {
                $newName = pathinfo($oldName, PATHINFO_FILENAME).'.webp';
                $newKey = $storeRelativeKeys ? trim($dir, '/').'/'.$newName : $newName;
                $map[$oldKey] = $newKey;
                if (! $storeRelativeKeys) {
                    // Also map full relative when settings store library-style paths under flat dirs.
                    $map[trim($dir, '/').'/'.$oldName] = trim($dir, '/').'/'.$newName;
                }
                $details[] = sprintf('[dry-run] %s → %s', $oldKey, $newKey);
                $converted++;
                continue;
            }

            $result = $this->webp->convertAbsolutePath($abs, ! $keepOriginals);
            if ($result === null) {
                $failed++;
                $details[] = sprintf('[fail] %s', $oldKey);
                continue;
            }

            $newName = $result['filename'];
            $newKey = $storeRelativeKeys ? trim($dir, '/').'/'.$newName : $newName;
            $map[$oldKey] = $newKey;
            if (! $storeRelativeKeys) {
                $map[trim($dir, '/').'/'.$oldName] = trim($dir, '/').'/'.$newName;
            }
            // Basename-only map for relative library paths that appear as bare filenames.
            $map[$oldName] = $newName;

            $details[] = sprintf('[ok] %s → %s', $oldKey, $newKey);
            $converted++;
        }

        return compact('converted', 'skipped', 'failed');
    }

    /**
     * @param  array<string, string>  $map
     */
    private function rewriteSettingsPaths(int $businessId, array $map): int
    {
        $raw = DB::table('storefront_settings')->where('business_id', $businessId)->value('value');
        if ($raw === null || $raw === '' || $raw === []) {
            return 0;
        }

        if (is_array($raw)) {
            $data = $raw;
        } else {
            $data = json_decode((string) $raw, true);
            if (! is_array($data)) {
                return 0;
            }
        }

        $count = 0;
        $rewritten = $this->rewriteValue($data, $map, $count);
        if ($count < 1) {
            return 0;
        }

        $payload = json_encode($rewritten, JSON_UNESCAPED_UNICODE);
        if (! is_string($payload) || $payload === '') {
            throw new \RuntimeException('Could not encode storefront settings after WebP rewrite.');
        }

        DB::table('storefront_settings')->where('business_id', $businessId)->update([
            'value' => $payload,
            'updated_at' => now(),
        ]);
        Cache::forget('storefront_settings_'.$businessId);

        return $count;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function countSettingsRewrites(int $businessId, array $map): int
    {
        $raw = DB::table('storefront_settings')->where('business_id', $businessId)->value('value');
        if ($raw === null || $raw === '' || $raw === []) {
            return 0;
        }

        if (is_array($raw)) {
            $data = $raw;
        } else {
            $data = json_decode((string) $raw, true);
            if (! is_array($data)) {
                return 0;
            }
        }

        $count = 0;
        $this->rewriteValue($data, $map, $count);

        return $count;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function rewriteMediaRows(int $businessId, array $map): int
    {
        $count = 0;
        $rows = StorefrontMedia::withTrashed()
            ->where('business_id', $businessId)
            ->where('kind', 'image')
            ->get();

        foreach ($rows as $row) {
            $path = str_replace('\\', '/', (string) $row->path);
            $basename = basename($path);
            $replacement = $map[$path] ?? $map[$basename] ?? null;
            if ($replacement === null) {
                continue;
            }

            // Prefer full relative path when map value is basename only.
            if (! str_contains($replacement, '/')) {
                $dir = str_contains($path, '/') ? dirname($path) : StorefrontMediaLibraryService::DIR.'/'.$businessId;
                $replacement = ($dir !== '.' ? $dir.'/' : '').$replacement;
            }

            if ($replacement === $path) {
                continue;
            }

            $abs = public_path('uploads/'.$replacement);
            $bytes = is_file($abs) ? (int) filesize($abs) : (int) $row->bytes;
            $checksum = is_file($abs) ? (hash_file('sha256', $abs) ?: $row->checksum) : $row->checksum;

            $row->path = $replacement;
            $row->mime = 'image/webp';
            $row->bytes = $bytes;
            $row->checksum = $checksum;
            $row->save();
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function countMediaRewrites(int $businessId, array $map): int
    {
        $count = 0;
        $rows = StorefrontMedia::withTrashed()
            ->where('business_id', $businessId)
            ->where('kind', 'image')
            ->get(['path']);

        foreach ($rows as $row) {
            $path = str_replace('\\', '/', (string) $row->path);
            $basename = basename($path);
            if (isset($map[$path]) || isset($map[$basename])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Recursively replace image path strings that match conversion map keys.
     *
     * @param  mixed  $value
     * @param  array<string, string>  $map
     * @return mixed
     */
    private function rewriteValue(mixed $value, array $map, int &$count): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->rewriteValue($v, $map, $count);
            }

            return $out;
        }

        if (! is_string($value) || $value === '') {
            return $value;
        }

        // External URLs are left alone.
        if (preg_match('#^(https?:)?//#i', $value) === 1) {
            return $value;
        }

        $normalized = str_replace('\\', '/', ltrim($value, '/'));
        if (isset($map[$normalized])) {
            $count++;

            return $map[$normalized];
        }

        $base = basename($normalized);
        if (isset($map[$base]) && ! str_contains($normalized, '/')) {
            $count++;

            return $map[$base];
        }

        // Path under uploads/storefront_* with convertible basename.
        if (isset($map[$base]) && (
            str_starts_with($normalized, 'storefront_')
            || str_starts_with($normalized, 'uploads/storefront_')
        )) {
            $dir = dirname($normalized);
            if (str_starts_with($dir, 'uploads/')) {
                $dir = substr($dir, strlen('uploads/'));
            }
            $count++;

            return ($dir !== '.' ? $dir.'/' : '').$map[$base];
        }

        return $value;
    }

    /**
     * @return list<int>
     */
    public function allBusinessIds(): array
    {
        return DB::table('storefront_settings')
            ->orderBy('business_id')
            ->pluck('business_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
