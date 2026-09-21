<?php

namespace App\Services\Storefront;

use App\StorefrontMedia;
use App\Support\ImageWebpConverter;
use App\Utils\Util;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
        private ImageWebpConverter $webp,
        private Util $util
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
     *   rewritten_catalog: int,
     *   rewritten_remote: int,
     *   dry_run: bool,
     *   details: list<string>
     * }
     */
    public function convertBusiness(
        int $businessId,
        bool $dryRun = false,
        bool $keepOriginals = false,
        string $scope = 'all'
    ): array {
        $map = []; // old relative path or basename => new basename or relative path
        $details = [];
        $converted = 0;
        $skipped = 0;
        $failed = 0;
        $rewrittenSettings = 0;
        $rewrittenMedia = 0;
        $rewrittenCatalog = 0;
        $rewrittenRemote = 0;

        $scope = in_array($scope, ['all', 'storefront', 'catalog'], true) ? $scope : 'all';
        $doStorefront = $scope === 'all' || $scope === 'storefront';
        $doCatalog = $scope === 'all' || $scope === 'catalog';

        if ($doStorefront) {
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

            if ($map !== []) {
                if (! $dryRun) {
                    $rewrittenSettings = $this->rewriteSettingsPaths($businessId, $map);
                    $rewrittenMedia = $this->rewriteMediaRows($businessId, $map);
                } else {
                    $rewrittenSettings = $this->countSettingsRewrites($businessId, $map);
                    $rewrittenMedia = $this->countMediaRewrites($businessId, $map);
                }
            }

            // Promo tiles / hero slides often store external WordPress URLs in media `url`.
            $remote = $this->ingestRemoteMediaUrls($businessId, $dryRun, $details);
            $converted += $remote['converted'];
            $skipped += $remote['skipped'];
            $failed += $remote['failed'];
            $rewrittenRemote = $remote['rewritten'];
        }

        if ($doCatalog) {
            $catalogMap = [];
            $catalogResult = $this->convertCatalogImages(
                $businessId,
                $dryRun,
                $keepOriginals,
                $catalogMap,
                $details
            );
            $converted += $catalogResult['converted'];
            $skipped += $catalogResult['skipped'];
            $failed += $catalogResult['failed'];

            if ($catalogMap !== []) {
                if (! $dryRun) {
                    $rewrittenCatalog = $this->rewriteCatalogRows($businessId, $catalogMap);
                } else {
                    $rewrittenCatalog = $this->countCatalogRewrites($businessId, $catalogMap);
                }
            }
        }

        return [
            'business_id' => $businessId,
            'converted' => $converted,
            'skipped' => $skipped,
            'failed' => $failed,
            'rewritten_settings' => $rewrittenSettings,
            'rewritten_media' => $rewrittenMedia,
            'rewritten_catalog' => $rewrittenCatalog,
            'rewritten_remote' => $rewrittenRemote,
            'dry_run' => $dryRun,
            'details' => $details,
        ];
    }

    /**
     * Convert product / category / brand / gallery files referenced by this business.
     *
     * @param  array<string, string>  $map  basename => new basename (scoped by upload dir via prefix keys)
     * @param  list<string>  $details
     * @return array{converted: int, skipped: int, failed: int}
     */
    private function convertCatalogImages(
        int $businessId,
        bool $dryRun,
        bool $keepOriginals,
        array &$map,
        array &$details
    ): array {
        $converted = 0;
        $skipped = 0;
        $failed = 0;

        $imgDir = trim((string) config('constants.product_img_path', 'img'), '/');
        $imgNames = $this->collectImgBasenames($businessId);
        $mediaNames = $this->collectMediaBasenames($businessId);

        foreach ($imgNames as $name) {
            $result = $this->convertOneCatalogFile(
                $imgDir,
                $name,
                $dryRun,
                $keepOriginals,
                $map,
                $details,
                'img'
            );
            $converted += $result['converted'];
            $skipped += $result['skipped'];
            $failed += $result['failed'];
        }

        foreach ($mediaNames as $name) {
            $result = $this->convertOneCatalogFile(
                'media',
                $name,
                $dryRun,
                $keepOriginals,
                $map,
                $details,
                'media'
            );
            $converted += $result['converted'];
            $skipped += $result['skipped'];
            $failed += $result['failed'];
        }

        return compact('converted', 'skipped', 'failed');
    }

    /**
     * @return list<string>
     */
    private function collectImgBasenames(int $businessId): array
    {
        $names = [];

        foreach (
            DB::table('products')
                ->where('business_id', $businessId)
                ->whereNotNull('image')
                ->where('image', '!=', '')
                ->pluck('image') as $image
        ) {
            $base = basename(str_replace('\\', '/', (string) $image));
            if ($base !== '') {
                $names[$base] = true;
            }
        }

        $categoryCols = ['image', 'shelf_banner', 'shelf_fg_image'];
        foreach ($categoryCols as $col) {
            if (! DB::getSchemaBuilder()->hasColumn('categories', $col)) {
                continue;
            }
            foreach (
                DB::table('categories')
                    ->where('business_id', $businessId)
                    ->whereNotNull($col)
                    ->where($col, '!=', '')
                    ->pluck($col) as $image
            ) {
                $base = basename(str_replace('\\', '/', (string) $image));
                if ($base !== '') {
                    $names[$base] = true;
                }
            }
        }

        if (DB::getSchemaBuilder()->hasTable('brands')) {
            foreach (
                DB::table('brands')
                    ->where('business_id', $businessId)
                    ->whereNotNull('image')
                    ->where('image', '!=', '')
                    ->pluck('image') as $image
            ) {
                $base = basename(str_replace('\\', '/', (string) $image));
                if ($base !== '') {
                    $names[$base] = true;
                }
            }
        }

        return array_keys($names);
    }

    /**
     * @return list<string>
     */
    private function collectMediaBasenames(int $businessId): array
    {
        if (! DB::getSchemaBuilder()->hasTable('media')) {
            return [];
        }

        $names = [];
        foreach (
            DB::table('media')
                ->where('business_id', $businessId)
                ->whereNotNull('file_name')
                ->where('file_name', '!=', '')
                ->pluck('file_name') as $fileName
        ) {
            $base = basename(str_replace('\\', '/', (string) $fileName));
            if ($base !== '') {
                $names[$base] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * @param  array<string, string>  $map
     * @param  list<string>  $details
     * @return array{converted: int, skipped: int, failed: int}
     */
    private function convertOneCatalogFile(
        string $dir,
        string $fileName,
        bool $dryRun,
        bool $keepOriginals,
        array &$map,
        array &$details,
        string $mapPrefix
    ): array {
        $converted = 0;
        $skipped = 0;
        $failed = 0;

        $fileName = basename(str_replace('\\', '/', $fileName));
        if ($fileName === '' || str_contains($fileName, '..')) {
            $skipped++;

            return compact('converted', 'skipped', 'failed');
        }

        $abs = public_path('uploads/'.trim($dir, '/').'/'.$fileName);
        if (! is_file($abs)) {
            $skipped++;
            $details[] = sprintf('[missing] %s/%s', $dir, $fileName);

            return compact('converted', 'skipped', 'failed');
        }

        if (! $this->webp->shouldConvert($abs)) {
            $skipped++;

            return compact('converted', 'skipped', 'failed');
        }

        $mapKey = $mapPrefix.':'.$fileName;

        if ($dryRun) {
            $newName = pathinfo($fileName, PATHINFO_FILENAME).'.webp';
            $map[$mapKey] = $newName;
            $details[] = sprintf('[dry-run] %s/%s → %s', $dir, $fileName, $newName);
            $converted++;

            return compact('converted', 'skipped', 'failed');
        }

        $result = $this->webp->convertAbsolutePath($abs, ! $keepOriginals);
        if ($result === null) {
            $failed++;
            $details[] = sprintf('[fail] %s/%s', $dir, $fileName);

            return compact('converted', 'skipped', 'failed');
        }

        $map[$mapKey] = $result['filename'];
        $details[] = sprintf('[ok] %s/%s → %s', $dir, $fileName, $result['filename']);
        $converted++;

        return compact('converted', 'skipped', 'failed');
    }

    /**
     * @param  array<string, string>  $map  keys like img:old.jpg / media:old.png
     */
    private function rewriteCatalogRows(int $businessId, array $map): int
    {
        $count = 0;
        $imgMap = $this->mapForPrefix($map, 'img');
        $mediaMap = $this->mapForPrefix($map, 'media');

        if ($imgMap !== []) {
            $count += $this->bulkReplaceColumn('products', 'image', $businessId, $imgMap);
            $count += $this->bulkReplaceColumn('categories', 'image', $businessId, $imgMap);
            $count += $this->bulkReplaceColumn('categories', 'shelf_banner', $businessId, $imgMap);
            $count += $this->bulkReplaceColumn('categories', 'shelf_fg_image', $businessId, $imgMap);
            if (DB::getSchemaBuilder()->hasTable('brands')) {
                $count += $this->bulkReplaceColumn('brands', 'image', $businessId, $imgMap);
            }
        }

        if ($mediaMap !== [] && DB::getSchemaBuilder()->hasTable('media')) {
            $count += $this->bulkReplaceColumn('media', 'file_name', $businessId, $mediaMap);
        }

        return $count;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function countCatalogRewrites(int $businessId, array $map): int
    {
        $count = 0;
        $imgMap = $this->mapForPrefix($map, 'img');
        $mediaMap = $this->mapForPrefix($map, 'media');

        foreach ($imgMap as $old => $new) {
            $count += (int) DB::table('products')->where('business_id', $businessId)->where('image', $old)->count();
            $count += (int) DB::table('categories')->where('business_id', $businessId)->where('image', $old)->count();
            $count += (int) DB::table('categories')->where('business_id', $businessId)->where('shelf_banner', $old)->count();
            $count += (int) DB::table('categories')->where('business_id', $businessId)->where('shelf_fg_image', $old)->count();
            if (DB::getSchemaBuilder()->hasTable('brands')) {
                $count += (int) DB::table('brands')->where('business_id', $businessId)->where('image', $old)->count();
            }
        }

        if ($mediaMap !== [] && DB::getSchemaBuilder()->hasTable('media')) {
            foreach ($mediaMap as $old => $new) {
                $count += (int) DB::table('media')->where('business_id', $businessId)->where('file_name', $old)->count();
            }
        }

        return $count;
    }

    /**
     * @param  array<string, string>  $map
     * @return array<string, string>
     */
    private function mapForPrefix(array $map, string $prefix): array
    {
        $out = [];
        $needle = $prefix.':';
        foreach ($map as $key => $value) {
            if (str_starts_with($key, $needle)) {
                $out[substr($key, strlen($needle))] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $map  old basename => new basename
     */
    private function bulkReplaceColumn(string $table, string $column, int $businessId, array $map): int
    {
        $count = 0;
        foreach ($map as $old => $new) {
            if ($old === '' || $old === $new) {
                continue;
            }
            $count += DB::table($table)
                ->where('business_id', $businessId)
                ->where($column, $old)
                ->update([$column => $new]);
        }

        return $count;
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
     * Download external media `url` values (promo tiles, hero slides, etc.), convert to WebP,
     * store under storefront_library/{businessId}/, and rewrite settings to local `image` paths.
     *
     * @param  list<string>  $details
     * @return array{converted: int, skipped: int, failed: int, rewritten: int}
     */
    private function ingestRemoteMediaUrls(int $businessId, bool $dryRun, array &$details): array
    {
        $converted = 0;
        $skipped = 0;
        $failed = 0;
        $rewritten = 0;

        $raw = DB::table('storefront_settings')->where('business_id', $businessId)->value('value');
        if ($raw === null || $raw === '' || $raw === []) {
            return compact('converted', 'skipped', 'failed', 'rewritten');
        }

        if (is_array($raw)) {
            $data = $raw;
        } else {
            $data = json_decode((string) $raw, true);
            if (! is_array($data)) {
                return compact('converted', 'skipped', 'failed', 'rewritten');
            }
        }

        $changed = false;
        $this->walkAndIngestRemoteMedia($data, $businessId, $dryRun, $details, $converted, $skipped, $failed, $rewritten, $changed);

        if ($changed && ! $dryRun) {
            $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
            if (! is_string($payload) || $payload === '') {
                throw new \RuntimeException('Could not encode storefront settings after remote WebP ingest.');
            }
            DB::table('storefront_settings')->where('business_id', $businessId)->update([
                'value' => $payload,
                'updated_at' => now(),
            ]);
            Cache::forget('storefront_settings_'.$businessId);
        }

        return compact('converted', 'skipped', 'failed', 'rewritten');
    }

    /**
     * @param  mixed  $node
     * @param  list<string>  $details
     */
    private function walkAndIngestRemoteMedia(
        mixed &$node,
        int $businessId,
        bool $dryRun,
        array &$details,
        int &$converted,
        int &$skipped,
        int &$failed,
        int &$rewritten,
        bool &$changed
    ): void {
        if (! is_array($node)) {
            return;
        }

        // Media row shape used by homepage sections: { image, url }.
        $hasImageKey = array_key_exists('image', $node);
        $hasUrlKey = array_key_exists('url', $node);
        if ($hasImageKey && $hasUrlKey) {
            $url = trim((string) ($node['url'] ?? ''));
            $image = trim((string) ($node['image'] ?? ''));
            if ($image === '' && $this->isRemoteImageUrl($url)) {
                $result = $this->downloadRemoteImageAsWebp($businessId, $url, $dryRun, $details);
                if ($result['status'] === 'ok') {
                    $converted++;
                    $rewritten++;
                    if (! $dryRun && ! empty($result['path'])) {
                        $node['image'] = $result['path'];
                        $node['url'] = '';
                        $changed = true;
                    }
                } elseif ($result['status'] === 'fail') {
                    $failed++;
                } else {
                    $skipped++;
                }
            }
        }

        foreach ($node as &$child) {
            $this->walkAndIngestRemoteMedia(
                $child,
                $businessId,
                $dryRun,
                $details,
                $converted,
                $skipped,
                $failed,
                $rewritten,
                $changed
            );
        }
        unset($child);
    }

    private function isRemoteImageUrl(string $url): bool
    {
        if (preg_match('#^https?://#i', $url) !== 1) {
            return false;
        }
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        // Includes WordPress-style ".jpg.webp" double extensions.
        return (bool) preg_match('/\.(jpe?g|png|gif|bmp|webp)(\.webp)?(\?|$)/i', $path);
    }

    /**
     * @param  list<string>  $details
     * @return array{status: 'ok'|'fail'|'skip', path?: string}
     */
    private function downloadRemoteImageAsWebp(
        int $businessId,
        string $url,
        bool $dryRun,
        array &$details
    ): array {
        if ($dryRun) {
            $details[] = sprintf('[dry-run] remote → library: %s', $url);
            return ['status' => 'ok', 'path' => StorefrontMediaLibraryService::DIR.'/'.$businessId.'/remote.webp'];
        }

        if (! $this->webp->isAvailable()) {
            $details[] = sprintf('[skip-remote] GD webp unavailable for %s', $url);
            return ['status' => 'skip'];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'GamesSpot-WebP-Converter/1.0'])
                ->get($url);
        } catch (\Throwable $e) {
            $details[] = sprintf('[fail-remote] %s (%s)', $url, $e->getMessage());
            return ['status' => 'fail'];
        }

        if (! $response->successful()) {
            $details[] = sprintf('[fail-remote] %s (HTTP %d)', $url, $response->status());
            return ['status' => 'fail'];
        }

        $body = $response->body();
        if ($body === '' || strlen($body) > StorefrontMediaLibraryService::MAX_BYTES) {
            $details[] = sprintf('[fail-remote] %s (empty or too large)', $url);
            return ['status' => 'fail'];
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        $ext = $this->extensionFromRemote($url, $contentType);
        if ($ext === null) {
            $details[] = sprintf('[skip-remote] not a raster image: %s', $url);
            return ['status' => 'skip'];
        }

        $relativeDir = StorefrontMediaLibraryService::DIR.'/'.$businessId;
        $this->util->ensurePublicUploadPermissions($relativeDir, null, true);
        $absDir = public_path('uploads/'.$relativeDir);
        if (! is_dir($absDir) && ! @mkdir($absDir, 0755, true) && ! is_dir($absDir)) {
            $details[] = sprintf('[fail-remote] cannot create %s', $relativeDir);
            return ['status' => 'fail'];
        }

        $tmpName = 'remote_'.time().'_'.Str::lower(Str::random(8)).'.'.$ext;
        $tmpAbs = $absDir.DIRECTORY_SEPARATOR.$tmpName;
        if (@file_put_contents($tmpAbs, $body) === false) {
            $details[] = sprintf('[fail-remote] write failed: %s', $url);
            return ['status' => 'fail'];
        }
        @chmod($tmpAbs, 0644);

        $finalName = $tmpName;
        $finalAbs = $tmpAbs;

        if ($ext !== 'webp') {
            $converted = $this->webp->convertAbsolutePath($tmpAbs, true);
            if ($converted === null) {
                @unlink($tmpAbs);
                $details[] = sprintf('[fail-remote] convert failed: %s', $url);
                return ['status' => 'fail'];
            }
            $finalName = $converted['filename'];
            $finalAbs = $converted['path'];
        }

        $relativePath = $relativeDir.'/'.$finalName;
        $checksum = hash_file('sha256', $finalAbs) ?: null;
        $bytes = (int) filesize($finalAbs);

        // Optional library row for admin media browser (ignore dup failures).
        try {
            if ($checksum) {
                $existing = StorefrontMedia::withTrashed()
                    ->where('business_id', $businessId)
                    ->where('checksum', $checksum)
                    ->first();
                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    // Prefer existing file path; remove duplicate we just wrote if different.
                    if ((string) $existing->path !== $relativePath && is_file($finalAbs)) {
                        @unlink($finalAbs);
                    }
                    $relativePath = (string) $existing->path;
                } else {
                    StorefrontMedia::create([
                        'business_id' => $businessId,
                        'path' => $relativePath,
                        'original_name' => mb_substr(basename((string) (parse_url($url, PHP_URL_PATH) ?: 'remote')), 0, 255),
                        'mime' => 'image/webp',
                        'kind' => 'image',
                        'bytes' => $bytes,
                        'checksum' => $checksum,
                        'uploaded_by' => null,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Settings path still works without a library row.
        }

        $this->util->ensurePublicUploadPermissions($relativeDir, basename($relativePath));
        $details[] = sprintf('[ok-remote] %s → %s', $url, $relativePath);

        return ['status' => 'ok', 'path' => $relativePath];
    }

    private function extensionFromRemote(string $url, string $contentType): ?string
    {
        if (str_contains($contentType, 'image/webp')) {
            return 'webp';
        }
        if (str_contains($contentType, 'image/png')) {
            return 'png';
        }
        if (str_contains($contentType, 'image/gif')) {
            return 'gif';
        }
        if (str_contains($contentType, 'image/jpeg') || str_contains($contentType, 'image/jpg')) {
            return 'jpg';
        }

        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        if (str_ends_with($path, '.webp') || str_contains($path, '.webp')) {
            return 'webp';
        }
        if (preg_match('/\.(jpe?g)(\?|$)/', $path)) {
            return 'jpg';
        }
        if (preg_match('/\.png(\?|$)/', $path)) {
            return 'png';
        }
        if (preg_match('/\.gif(\?|$)/', $path)) {
            return 'gif';
        }
        if (preg_match('/\.bmp(\?|$)/', $path)) {
            return 'bmp';
        }

        // Content-Type image/* without a known subtype — try jpeg decode path.
        if (str_starts_with($contentType, 'image/') && ! str_contains($contentType, 'svg')) {
            return 'jpg';
        }

        return null;
    }

    /**
     * @return list<int>
     */
    public function allBusinessIds(): array
    {
        $ids = DB::table('storefront_settings')->pluck('business_id');

        if (DB::getSchemaBuilder()->hasTable('products')) {
            $ids = $ids->merge(DB::table('products')->distinct()->pluck('business_id'));
        }
        if (DB::getSchemaBuilder()->hasTable('categories')) {
            $ids = $ids->merge(DB::table('categories')->distinct()->pluck('business_id'));
        }

        return $ids
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
