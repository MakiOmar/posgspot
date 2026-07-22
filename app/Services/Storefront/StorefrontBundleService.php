<?php

namespace App\Services\Storefront;

use App\BrandTranslation;
use App\Brands;
use App\BusinessLocation;
use App\Category;
use App\CategoryTranslation;
use App\Coupon;
use App\Product;
use App\ProductTranslation;
use App\Services\Storefront\Shipping\ShippingZoneRepository;
use App\StorefrontShippingClass;
use App\StorefrontShippingMethod;
use App\StorefrontShippingZone;
use App\StorefrontShippingZoneLocation;
use App\Variation;
use App\VariationTranslation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Full storefront config package (settings + shipping + media + coupons +
 * catalog overlays + translations). Tuned for low memory / short DB locks:
 * compact JSON, ZipArchive::addFile (no media buffering), stream media in
 * without extracting the archive, preload lookup maps, bulk inserts.
 */
class StorefrontBundleService
{
    public const FORMAT = 'storefront_bundle';

    public const VERSION = 2;

    private const INSERT_CHUNK = 200;

    private const LOOKUP_CHUNK = 500;

    /** @var list<string> */
    private const MEDIA_DIRS = [
        'storefront_homepage',
        'storefront_payment_icons',
        'storefront_banners',
        'img',
    ];

    /** @var array<string, true> */
    private const MEDIA_DIR_LOOKUP = [
        'storefront_homepage' => true,
        'storefront_payment_icons' => true,
        'storefront_banners' => true,
        'img' => true,
    ];

    public function __construct(
        private StorefrontSettingService $settings,
        private ShippingZoneRepository $shippingZones,
    ) {
    }

    /**
     * Build a ZIP on disk and return its absolute path (caller deletes after send).
     */
    public function exportToTempZip(int $businessId): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('PHP zip extension is required for storefront export.');
        }

        $this->disableQueryLog();

        $tmp = tempnam(sys_get_temp_dir(), 'sf_bundle_');
        if ($tmp === false) {
            throw new \RuntimeException('Could not create temporary export file.');
        }
        $zipPath = $tmp.'.zip';
        $manifestPath = $tmp.'.json';
        @unlink($tmp);

        try {
            $manifest = $this->buildManifest($businessId);
            $mediaFiles = $this->collectMediaFiles($manifest);

            // Compact JSON on disk — avoid holding both the array and a pretty-printed string.
            if (file_put_contents(
                $manifestPath,
                json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ) === false) {
                throw new \RuntimeException('Could not write storefront export manifest.');
            }
            unset($manifest);

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Could not create storefront export ZIP.');
            }

            // addFile stores a path reference; bytes are read at close() — low peak RAM.
            $zip->addFile($manifestPath, 'manifest.json');
            foreach ($mediaFiles as $relative => $absolute) {
                $zip->addFile($absolute, 'media/'.$relative);
            }
            unset($mediaFiles);

            if (! $zip->close()) {
                throw new \RuntimeException('Could not finalize storefront export ZIP.');
            }
        } finally {
            @unlink($manifestPath);
        }

        return $zipPath;
    }

    /**
     * Import a ZIP bundle or legacy settings JSON payload.
     *
     * @return array{sections: list<string>, details: array<string, mixed>}
     */
    public function importPath(int $businessId, string $path, string $extension): array
    {
        $this->disableQueryLog();
        $extension = strtolower($extension);

        if ($extension === 'zip') {
            return $this->importZip($businessId, $path);
        }

        if (in_array($extension, ['json', 'txt'], true)) {
            $raw = @file_get_contents($path);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            unset($raw);
            if (! is_array($decoded)) {
                throw new \InvalidArgumentException('Import file must be valid JSON.');
            }

            if (($decoded['format'] ?? null) === self::FORMAT) {
                return $this->importManifest($businessId, $decoded, null);
            }

            $result = $this->settings->importFromPayload($businessId, $decoded);

            return [
                'sections' => ['settings'],
                'details' => $result,
            ];
        }

        throw new \InvalidArgumentException('Import file must be a .zip or .json export.');
    }

    /**
     * @return array{sections: list<string>, details: array<string, mixed>}
     */
    private function importZip(int $businessId, string $zipPath): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('PHP zip extension is required for storefront import.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \InvalidArgumentException('Could not open ZIP archive.');
        }

        try {
            $manifestRaw = $zip->getFromName('manifest.json');
            if ($manifestRaw === false) {
                throw new \InvalidArgumentException('ZIP is missing manifest.json.');
            }

            $manifest = json_decode($manifestRaw, true);
            unset($manifestRaw);
            if (! is_array($manifest)) {
                throw new \InvalidArgumentException('manifest.json is not valid JSON.');
            }

            // Stream media straight into uploads/ — no full archive extract (disk + RAM).
            $mediaStats = $this->importMediaFromZip($zip);
        } finally {
            $zip->close();
        }

        $result = $this->importManifest($businessId, $manifest, null);
        unset($manifest);

        if (($mediaStats['copied'] ?? 0) > 0 || ($mediaStats['skipped'] ?? 0) > 0) {
            $result['details']['media'] = $mediaStats;
            if (! in_array('media', $result['sections'], true)) {
                array_unshift($result['sections'], 'media');
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array{sections: list<string>, details: array<string, mixed>}
     */
    private function importManifest(int $businessId, array $manifest, ?string $mediaDir): array
    {
        $format = $manifest['format'] ?? null;
        if ($format !== null && $format !== self::FORMAT && $format !== StorefrontSettingService::EXPORT_FORMAT) {
            throw new \InvalidArgumentException('Unrecognized import format.');
        }

        $version = isset($manifest['version']) ? (int) $manifest['version'] : self::VERSION;
        if ($version > self::VERSION) {
            throw new \InvalidArgumentException(
                'Import file version '.$version.' is newer than this app supports ('.self::VERSION.').'
            );
        }

        $sections = [];
        $details = [];

        // File I/O stays outside the DB transaction to avoid long row locks.
        if ($mediaDir !== null && is_dir($mediaDir)) {
            $details['media'] = $this->importMediaFromDirectory($mediaDir);
            $sections[] = 'media';
        }

        $locationIdMap = $this->buildLocationIdMap($businessId, $manifest['locations'] ?? []);

        DB::transaction(function () use ($businessId, $manifest, $locationIdMap, &$sections, &$details) {
            if (isset($manifest['settings']) && is_array($manifest['settings'])) {
                $settingsPayload = $this->remapSettingsLocationIds($manifest['settings'], $locationIdMap);
                $details['settings'] = $this->settings->importFromPayload($businessId, [
                    'format' => StorefrontSettingService::EXPORT_FORMAT,
                    'version' => StorefrontSettingService::EXPORT_VERSION,
                    'settings' => $settingsPayload,
                ]);
                $sections[] = 'settings';
            }

            if (isset($manifest['locations']) && is_array($manifest['locations'])) {
                $details['locations'] = $this->importLocationOverlays($businessId, $manifest['locations']);
                $sections[] = 'locations';
            }

            $classIdMap = [];
            if (isset($manifest['shipping']) && is_array($manifest['shipping'])) {
                $details['shipping'] = $this->importShipping($businessId, $manifest['shipping'], $locationIdMap, $classIdMap);
                $sections[] = 'shipping';
            }

            if (isset($manifest['coupons']) && is_array($manifest['coupons'])) {
                $details['coupons'] = $this->importCoupons($businessId, $manifest['coupons']);
                $sections[] = 'coupons';
            }

            if (isset($manifest['catalog_overlays']) && is_array($manifest['catalog_overlays'])) {
                $details['catalog_overlays'] = $this->importCatalogOverlays(
                    $businessId,
                    $manifest['catalog_overlays'],
                    $classIdMap
                );
                $sections[] = 'catalog_overlays';
            }

            if (isset($manifest['translations']) && is_array($manifest['translations'])) {
                $details['translations'] = $this->importTranslations($businessId, $manifest['translations']);
                $sections[] = 'translations';
            }
        });

        if ($sections === []) {
            throw new \InvalidArgumentException('Import file contains no storefront data sections.');
        }

        return [
            'sections' => $sections,
            'details' => $details,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildManifest(int $businessId): array
    {
        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'business_id' => $businessId,
            'settings' => $this->settings->redactSecretsForExport($this->settings->get($businessId)),
            'locations' => $this->exportLocations($businessId),
            'shipping' => $this->exportShipping($businessId),
            'coupons' => $this->exportCoupons($businessId),
            'catalog_overlays' => $this->exportCatalogOverlays($businessId),
            'translations' => $this->exportTranslations($businessId),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportLocations(int $businessId): array
    {
        $out = [];
        foreach (
            BusinessLocation::where('business_id', $businessId)
                ->orderBy('id')
                ->toBase()
                ->select(['id', 'name', 'storefront_address'])
                ->cursor() as $loc
        ) {
            $out[] = [
                'id' => (int) $loc->id,
                'name' => (string) $loc->name,
                'storefront_address' => (string) ($loc->storefront_address ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array{classes: list<array<string, mixed>>, zones: list<array<string, mixed>>}
     */
    private function exportShipping(int $businessId): array
    {
        $classes = [];
        $classIdToSlug = [];
        foreach (
            StorefrontShippingClass::where('business_id', $businessId)
                ->orderBy('id')
                ->toBase()
                ->select(['id', 'name', 'slug'])
                ->cursor() as $class
        ) {
            $slug = trim((string) ($class->slug ?? ''));
            if ($slug === '') {
                $slug = Str::slug((string) $class->name) ?: ('class-'.$class->id);
            }
            $classes[] = [
                'id' => (int) $class->id,
                'name' => (string) $class->name,
                'slug' => $slug,
            ];
            $classIdToSlug[(int) $class->id] = $slug;
        }

        $zones = [];
        $zoneModels = StorefrontShippingZone::where('business_id', $businessId)
            ->with([
                'locations:id,zone_id,type,code',
                'methods:id,zone_id,type,title,title_i18n,settings,sort_order,is_enabled',
            ])
            ->orderBy('priority')
            ->orderBy('id')
            ->get(['id', 'name', 'priority', 'is_enabled', 'is_catch_all']);

        foreach ($zoneModels as $zone) {
            $methods = [];
            foreach ($zone->methods->sortBy([['sort_order', 'asc'], ['id', 'asc']]) as $method) {
                $methods[] = [
                    'type' => (string) $method->type,
                    'title' => (string) $method->title,
                    'title_i18n' => $method->title_i18n ?? [],
                    'settings' => $this->exportMethodSettings($method->settings ?? [], $classIdToSlug),
                    'sort_order' => (int) $method->sort_order,
                    'is_enabled' => (bool) $method->is_enabled,
                ];
            }

            $locations = [];
            foreach ($zone->locations as $loc) {
                $locations[] = [
                    'type' => (string) $loc->type,
                    'code' => (string) $loc->code,
                ];
            }

            $zones[] = [
                'name' => (string) $zone->name,
                'priority' => (int) $zone->priority,
                'is_enabled' => (bool) $zone->is_enabled,
                'is_catch_all' => (bool) $zone->is_catch_all,
                'locations' => $locations,
                'methods' => $methods,
            ];
        }
        unset($zoneModels);

        return [
            'classes' => $classes,
            'zones' => $zones,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, string>  $classIdToSlug
     * @return array<string, mixed>
     */
    private function exportMethodSettings(array $settings, array $classIdToSlug): array
    {
        if (isset($settings['class_costs']) && is_array($settings['class_costs'])) {
            $bySlug = [];
            foreach ($settings['class_costs'] as $classId => $cost) {
                $slug = $classIdToSlug[(int) $classId] ?? null;
                if ($slug === null) {
                    continue;
                }
                $bySlug[$slug] = $cost;
            }
            unset($settings['class_costs']);
            $settings['class_costs_by_slug'] = $bySlug;
        }

        return $settings;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportCoupons(int $businessId): array
    {
        $out = [];
        Coupon::where('business_id', $businessId)
            ->whereIn('channel', [Coupon::CHANNEL_STOREFRONT, Coupon::CHANNEL_BOTH])
            ->with(['categories:id,slug', 'variations:id,sub_sku'])
            ->orderBy('id')
            ->chunkById(100, function ($coupons) use (&$out) {
                foreach ($coupons as $coupon) {
                    $out[] = [
                        'code' => (string) $coupon->code,
                        'name' => (string) $coupon->name,
                        'description' => $coupon->description,
                        'type' => (string) $coupon->type,
                        'discount_amount' => (float) $coupon->discount_amount,
                        'max_discount_amount' => $coupon->max_discount_amount,
                        'min_order_subtotal' => (float) $coupon->min_order_subtotal,
                        'starts_at' => optional($coupon->starts_at)?->toIso8601String(),
                        'ends_at' => optional($coupon->ends_at)?->toIso8601String(),
                        'is_active' => (bool) $coupon->is_active,
                        'channel' => (string) $coupon->channel,
                        'max_uses_total' => $coupon->max_uses_total,
                        'max_uses_per_customer' => $coupon->max_uses_per_customer,
                        'first_order_only' => (bool) $coupon->first_order_only,
                        'exclude_sale_items' => (bool) $coupon->exclude_sale_items,
                        'stack_with_reward_points' => (bool) $coupon->stack_with_reward_points,
                        'applies_to' => (string) $coupon->applies_to,
                        'category_slugs' => $coupon->categories->pluck('slug')->filter()->values()->all(),
                        'variation_skus' => $coupon->variations->pluck('sub_sku')->filter()->values()->all(),
                    ];
                }
            });

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function exportCatalogOverlays(int $businessId): array
    {
        $categories = [];
        foreach (
            Category::where('business_id', $businessId)
                ->where('category_type', 'product')
                ->whereNotNull('slug')
                ->where('slug', '!=', '')
                ->where(function ($q) {
                    $q->where('show_on_homepage_shelf', 1)
                        ->orWhereNotNull('image')
                        ->orWhereNotNull('shelf_banner')
                        ->orWhereNotNull('shelf_fg_image')
                        ->orWhereNotNull('shelf_heading')
                        ->orWhereNotNull('shelf_banner_text')
                        ->orWhereNotNull('shelf_banner_link');
                })
                ->toBase()
                ->select([
                    'slug', 'image', 'show_on_homepage_shelf', 'homepage_shelf_sort',
                    'shelf_banner', 'shelf_fg_image', 'shelf_heading', 'shelf_banner_kicker',
                    'shelf_banner_text', 'shelf_button_text', 'shelf_banner_link', 'shelf_view_more_label',
                ])
                ->cursor() as $c
        ) {
            $categories[] = [
                'slug' => (string) $c->slug,
                'image' => $c->image,
                'show_on_homepage_shelf' => (bool) $c->show_on_homepage_shelf,
                'homepage_shelf_sort' => (int) ($c->homepage_shelf_sort ?? 0),
                'shelf_banner' => $c->shelf_banner,
                'shelf_fg_image' => $c->shelf_fg_image,
                'shelf_heading' => $c->shelf_heading,
                'shelf_banner_kicker' => $c->shelf_banner_kicker,
                'shelf_banner_text' => $c->shelf_banner_text,
                'shelf_button_text' => $c->shelf_button_text,
                'shelf_banner_link' => $c->shelf_banner_link,
                'shelf_view_more_label' => $c->shelf_view_more_label,
            ];
        }

        $brands = [];
        foreach (
            Brands::where('business_id', $businessId)
                ->whereNotNull('slug')
                ->where('slug', '!=', '')
                ->whereNotNull('image')
                ->where('image', '!=', '')
                ->toBase()
                ->select(['slug', 'image'])
                ->cursor() as $b
        ) {
            $brands[] = [
                'slug' => (string) $b->slug,
                'image' => $b->image,
            ];
        }

        $classIdToSlug = StorefrontShippingClass::where('business_id', $businessId)
            ->toBase()
            ->select(['id', 'name', 'slug'])
            ->get()
            ->mapWithKeys(function ($class) {
                $slug = trim((string) ($class->slug ?? ''));
                if ($slug === '') {
                    $slug = Str::slug((string) $class->name) ?: ('class-'.$class->id);
                }

                return [(int) $class->id => $slug];
            })
            ->all();

        $products = [];
        foreach (
            Product::where('business_id', $businessId)
                ->where(function ($q) {
                    $q->where('is_storefront_featured', 1)
                        ->orWhereNotNull('shipping_class_id');
                })
                ->toBase()
                ->select(['sku', 'is_storefront_featured', 'shipping_class_id'])
                ->cursor() as $p
        ) {
            $sku = (string) $p->sku;
            if ($sku === '') {
                continue;
            }
            $products[] = [
                'sku' => $sku,
                'is_storefront_featured' => (bool) $p->is_storefront_featured,
                'shipping_class_slug' => ! empty($p->shipping_class_id)
                    ? ($classIdToSlug[(int) $p->shipping_class_id] ?? null)
                    : null,
            ];
        }

        $variations = [];
        foreach (
            Variation::query()
                ->join('products', 'variations.product_id', '=', 'products.id')
                ->where('products.business_id', $businessId)
                ->whereNotNull('variations.storefront_sale_price_inc_tax')
                ->whereNull('variations.deleted_at')
                ->toBase()
                ->select(['variations.sub_sku', 'variations.storefront_sale_price_inc_tax'])
                ->cursor() as $v
        ) {
            $subSku = (string) $v->sub_sku;
            if ($subSku === '') {
                continue;
            }
            $variations[] = [
                'sub_sku' => $subSku,
                'storefront_sale_price_inc_tax' => (float) $v->storefront_sale_price_inc_tax,
            ];
        }

        return [
            'categories' => $categories,
            'brands' => $brands,
            'products' => $products,
            'variations' => $variations,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exportTranslations(int $businessId): array
    {
        $products = [];
        foreach (
            ProductTranslation::query()
                ->join('products', 'product_translations.product_id', '=', 'products.id')
                ->where('products.business_id', $businessId)
                ->where('products.sku', '!=', '')
                ->toBase()
                ->select([
                    'products.sku as product_sku',
                    'product_translations.locale',
                    'product_translations.name',
                    'product_translations.product_description',
                    'product_translations.slug',
                ])
                ->cursor() as $row
        ) {
            $products[] = [
                'product_sku' => (string) $row->product_sku,
                'locale' => (string) $row->locale,
                'name' => (string) $row->name,
                'product_description' => $row->product_description,
                'slug' => $row->slug,
            ];
        }

        $categories = [];
        foreach (
            CategoryTranslation::query()
                ->join('categories', 'category_translations.category_id', '=', 'categories.id')
                ->where('categories.business_id', $businessId)
                ->whereNotNull('categories.slug')
                ->where('categories.slug', '!=', '')
                ->toBase()
                ->select([
                    'categories.slug as category_slug',
                    'category_translations.locale',
                    'category_translations.name',
                    'category_translations.slug',
                ])
                ->cursor() as $row
        ) {
            $categories[] = [
                'category_slug' => (string) $row->category_slug,
                'locale' => (string) $row->locale,
                'name' => (string) $row->name,
                'slug' => $row->slug,
            ];
        }

        $brands = [];
        foreach (
            BrandTranslation::query()
                ->join('brands', 'brand_translations.brand_id', '=', 'brands.id')
                ->where('brands.business_id', $businessId)
                ->whereNotNull('brands.slug')
                ->where('brands.slug', '!=', '')
                ->toBase()
                ->select([
                    'brands.slug as brand_slug',
                    'brand_translations.locale',
                    'brand_translations.name',
                ])
                ->cursor() as $row
        ) {
            $brands[] = [
                'brand_slug' => (string) $row->brand_slug,
                'locale' => (string) $row->locale,
                'name' => (string) $row->name,
            ];
        }

        $variations = [];
        foreach (
            VariationTranslation::query()
                ->join('variations', 'variation_translations.variation_id', '=', 'variations.id')
                ->join('products', 'variations.product_id', '=', 'products.id')
                ->where('products.business_id', $businessId)
                ->whereNull('variations.deleted_at')
                ->where('variations.sub_sku', '!=', '')
                ->toBase()
                ->select([
                    'variations.sub_sku',
                    'variation_translations.locale',
                    'variation_translations.name',
                ])
                ->cursor() as $row
        ) {
            $variations[] = [
                'sub_sku' => (string) $row->sub_sku,
                'locale' => (string) $row->locale,
                'name' => (string) $row->name,
            ];
        }

        return [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'variations' => $variations,
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, string> relative path inside media/ => absolute path
     */
    private function collectMediaFiles(array $manifest): array
    {
        $basenames = [];
        $this->collectImageBasenames($manifest['settings'] ?? [], $basenames);

        foreach (($manifest['catalog_overlays']['categories'] ?? []) as $row) {
            foreach (['image', 'shelf_banner', 'shelf_fg_image'] as $field) {
                $val = trim((string) ($row[$field] ?? ''));
                if ($val !== '') {
                    $basenames[$val] = true;
                }
            }
        }
        foreach (($manifest['catalog_overlays']['brands'] ?? []) as $row) {
            $val = trim((string) ($row['image'] ?? ''));
            if ($val !== '') {
                $basenames[$val] = true;
            }
        }

        $files = [];
        $uploadsRoot = public_path('uploads');

        foreach (array_keys($basenames) as $basename) {
            $basename = basename((string) $basename);
            if ($basename === '' || $basename === '.' || $basename === '..') {
                continue;
            }
            foreach (self::MEDIA_DIRS as $dir) {
                $absolute = $uploadsRoot.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.$basename;
                if (is_file($absolute)) {
                    $files[$dir.'/'.$basename] = $absolute;
                    break;
                }
            }
        }

        return $files;
    }

    /**
     * @param  mixed  $node
     * @param  array<string, true>  $out
     */
    private function collectImageBasenames(mixed $node, array &$out): void
    {
        if (! is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            if (is_string($key) && in_array($key, ['image', 'shelf_banner', 'shelf_fg_image'], true)
                && is_string($value)
                && $value !== ''
                && ! str_contains($value, '/')
                && ! str_contains($value, '\\')
                && ! str_starts_with($value, 'data:')
                && ! preg_match('#^https?://#i', $value)
            ) {
                $out[basename($value)] = true;
            } elseif (is_array($value)) {
                $this->collectImageBasenames($value, $out);
            }
        }
    }

    /**
     * Copy media entries from an open ZIP directly into public/uploads.
     *
     * @return array{copied: int, skipped: int}
     */
    private function importMediaFromZip(ZipArchive $zip): array
    {
        $copied = 0;
        $skipped = 0;
        $uploadsRoot = public_path('uploads');
        $num = $zip->numFiles;

        for ($i = 0; $i < $num; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_string($name) || ! str_starts_with($name, 'media/')) {
                continue;
            }
            if (str_ends_with($name, '/')) {
                continue;
            }

            $relative = substr($name, strlen('media/'));
            $parts = explode('/', str_replace('\\', '/', $relative));
            if (count($parts) !== 2) {
                $skipped++;
                continue;
            }
            [$dir, $file] = $parts;
            if (! isset(self::MEDIA_DIR_LOOKUP[$dir])) {
                $skipped++;
                continue;
            }
            $safe = basename($file);
            if ($safe === '' || $safe !== $file || $safe === '.' || $safe === '..') {
                $skipped++;
                continue;
            }

            $targetDir = $uploadsRoot.DIRECTORY_SEPARATOR.$dir;
            if (! is_dir($targetDir) && ! @mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
                $skipped++;
                continue;
            }

            $target = $targetDir.DIRECTORY_SEPARATOR.$safe;
            $stream = $zip->getStream($name);
            if ($stream === false) {
                $skipped++;
                continue;
            }

            $out = @fopen($target, 'wb');
            if ($out === false) {
                fclose($stream);
                $skipped++;
                continue;
            }

            $bytes = stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);

            if ($bytes === false) {
                @unlink($target);
                $skipped++;
            } else {
                $copied++;
            }
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }

    /**
     * @return array{copied: int, skipped: int}
     */
    private function importMediaFromDirectory(string $mediaDir): array
    {
        $copied = 0;
        $skipped = 0;

        foreach (self::MEDIA_DIRS as $dir) {
            $sourceDir = $mediaDir.DIRECTORY_SEPARATOR.$dir;
            if (! is_dir($sourceDir)) {
                continue;
            }

            $targetDir = public_path('uploads/'.$dir);
            if (! is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }

            foreach (scandir($sourceDir) ?: [] as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $safe = basename($file);
                if ($safe !== $file) {
                    $skipped++;
                    continue;
                }
                $from = $sourceDir.DIRECTORY_SEPARATOR.$safe;
                if (! is_file($from)) {
                    continue;
                }
                if (@copy($from, $targetDir.DIRECTORY_SEPARATOR.$safe)) {
                    $copied++;
                } else {
                    $skipped++;
                }
            }
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }

    /**
     * @param  list<array<string, mixed>>  $exportedLocations
     * @return array<int, int>
     */
    private function buildLocationIdMap(int $businessId, array $exportedLocations): array
    {
        $byName = [];
        $targetIds = [];
        foreach (
            BusinessLocation::where('business_id', $businessId)
                ->toBase()
                ->select(['id', 'name'])
                ->cursor() as $loc
        ) {
            $id = (int) $loc->id;
            $byName[mb_strtolower(trim((string) $loc->name))] = $id;
            $targetIds[$id] = true;
        }

        $map = [];
        foreach ($exportedLocations as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sourceId = (int) ($row['id'] ?? 0);
            $name = mb_strtolower(trim((string) ($row['name'] ?? '')));
            if ($sourceId <= 0 || $name === '') {
                continue;
            }
            if (isset($byName[$name])) {
                $map[$sourceId] = $byName[$name];
            } elseif (isset($targetIds[$sourceId])) {
                $map[$sourceId] = $sourceId;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, int>  $locationIdMap
     * @return array<string, mixed>
     */
    private function remapSettingsLocationIds(array $settings, array $locationIdMap): array
    {
        if (isset($settings['selling_location_ids']) && is_array($settings['selling_location_ids'])) {
            $mapped = [];
            foreach ($settings['selling_location_ids'] as $id) {
                $mappedId = $locationIdMap[(int) $id] ?? null;
                if ($mappedId !== null) {
                    $mapped[] = $mappedId;
                }
            }
            $settings['selling_location_ids'] = array_values($mapped);
        }

        if (array_key_exists('default_fulfillment_location_id', $settings)) {
            $fid = $settings['default_fulfillment_location_id'];
            if ($fid !== null && $fid !== '') {
                $settings['default_fulfillment_location_id'] = $locationIdMap[(int) $fid] ?? null;
            }
        }

        return $settings;
    }

    /**
     * @param  list<array<string, mixed>>  $locations
     * @return array{updated: int}
     */
    private function importLocationOverlays(int $businessId, array $locations): array
    {
        $byName = BusinessLocation::where('business_id', $businessId)
            ->get(['id', 'name', 'storefront_address'])
            ->keyBy(fn (BusinessLocation $loc) => mb_strtolower(trim((string) $loc->name)));

        $updated = 0;
        foreach ($locations as $row) {
            if (! is_array($row) || ! array_key_exists('storefront_address', $row)) {
                continue;
            }
            $name = mb_strtolower(trim((string) ($row['name'] ?? '')));
            if ($name === '' || ! isset($byName[$name])) {
                continue;
            }
            /** @var BusinessLocation $loc */
            $loc = $byName[$name];
            $loc->storefront_address = (string) ($row['storefront_address'] ?? '');
            $loc->save();
            $updated++;
        }

        return ['updated' => $updated];
    }

    /**
     * @param  array<string, mixed>  $shipping
     * @param  array<int, int>  $locationIdMap
     * @param  array<string, int>  $classIdMap
     * @return array{classes: int, zones: int, methods: int}
     */
    private function importShipping(
        int $businessId,
        array $shipping,
        array $locationIdMap,
        array &$classIdMap
    ): array {
        $now = now();
        $classes = is_array($shipping['classes'] ?? null) ? $shipping['classes'] : [];
        $zones = is_array($shipping['zones'] ?? null) ? $shipping['zones'] : [];

        $existingClasses = StorefrontShippingClass::where('business_id', $businessId)
            ->get(['id', 'slug', 'name'])
            ->keyBy(function (StorefrontShippingClass $class) {
                $slug = trim((string) ($class->slug ?? ''));

                return $slug !== '' ? $slug : (Str::slug((string) $class->name) ?: ('class-'.$class->id));
            });

        foreach ($classes as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug === '' && $name !== '') {
                $slug = Str::slug($name);
            }
            if ($slug === '') {
                continue;
            }

            $class = $existingClasses->get($slug) ?? new StorefrontShippingClass();
            $class->business_id = $businessId;
            $class->name = $name !== '' ? $name : $slug;
            $class->slug = $slug;
            $class->save();
            $classIdMap[$slug] = (int) $class->id;
            $existingClasses[$slug] = $class;
        }

        StorefrontShippingZone::where('business_id', $businessId)->delete();

        $zoneCount = 0;
        $methodCount = 0;
        $locationRows = [];
        $methodRows = [];

        foreach ($zones as $zoneRow) {
            if (! is_array($zoneRow)) {
                continue;
            }

            $zoneId = StorefrontShippingZone::query()->insertGetId([
                'business_id' => $businessId,
                'name' => trim((string) ($zoneRow['name'] ?? 'Zone')) ?: 'Zone',
                'priority' => (int) ($zoneRow['priority'] ?? 50),
                'is_enabled' => ! empty($zoneRow['is_enabled']) ? 1 : 0,
                'is_catch_all' => ! empty($zoneRow['is_catch_all']) ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $zoneCount++;

            foreach ((array) ($zoneRow['locations'] ?? []) as $locRow) {
                if (! is_array($locRow)) {
                    continue;
                }
                $code = trim((string) ($locRow['code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $locationRows[] = [
                    'zone_id' => $zoneId,
                    'type' => ($locRow['type'] ?? '') === 'state' ? 'state' : 'country',
                    'code' => $code,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach ((array) ($zoneRow['methods'] ?? []) as $methodRow) {
                if (! is_array($methodRow)) {
                    continue;
                }
                $type = (string) ($methodRow['type'] ?? StorefrontShippingMethod::TYPE_FLAT_RATE);
                $settings = is_array($methodRow['settings'] ?? null) ? $methodRow['settings'] : [];
                $settings = $this->importMethodSettings($settings, $classIdMap, $locationIdMap);

                $methodRows[] = [
                    'zone_id' => $zoneId,
                    'type' => $type,
                    'title' => trim((string) ($methodRow['title'] ?? $type)) ?: $type,
                    'title_i18n' => json_encode(
                        is_array($methodRow['title_i18n'] ?? null) ? $methodRow['title_i18n'] : [],
                        JSON_UNESCAPED_UNICODE
                    ),
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                    'sort_order' => (int) ($methodRow['sort_order'] ?? 0),
                    'is_enabled' => (array_key_exists('is_enabled', $methodRow) ? ! empty($methodRow['is_enabled']) : true) ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $methodCount++;
            }
        }

        foreach (array_chunk($locationRows, self::INSERT_CHUNK) as $chunk) {
            DB::table('storefront_shipping_zone_locations')->insert($chunk);
        }
        foreach (array_chunk($methodRows, self::INSERT_CHUNK) as $chunk) {
            DB::table('storefront_shipping_methods')->insert($chunk);
        }

        $this->shippingZones->flush($businessId);

        return [
            'classes' => count($classIdMap),
            'zones' => $zoneCount,
            'methods' => $methodCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, int>  $classIdMap
     * @param  array<int, int>  $locationIdMap
     * @return array<string, mixed>
     */
    private function importMethodSettings(array $settings, array $classIdMap, array $locationIdMap): array
    {
        $bySlug = $settings['class_costs_by_slug'] ?? null;
        if (is_array($bySlug)) {
            $costs = [];
            foreach ($bySlug as $slug => $cost) {
                if (isset($classIdMap[(string) $slug])) {
                    $costs[$classIdMap[(string) $slug]] = $cost;
                }
            }
            $settings['class_costs'] = $costs;
            unset($settings['class_costs_by_slug']);
        }

        if (isset($settings['location_ids']) && is_array($settings['location_ids'])) {
            $mapped = [];
            foreach ($settings['location_ids'] as $id) {
                $id = (int) $id;
                if (isset($locationIdMap[$id])) {
                    $mapped[] = $locationIdMap[$id];
                } elseif ($locationIdMap === []) {
                    $mapped[] = $id;
                }
            }
            $settings['location_ids'] = array_values(array_unique($mapped));
        }

        return $settings;
    }

    /**
     * @param  list<array<string, mixed>>  $coupons
     * @return array{upserted: int}
     */
    private function importCoupons(int $businessId, array $coupons): array
    {
        if ($coupons === []) {
            return ['upserted' => 0];
        }

        $allCategorySlugs = [];
        $allVariationSkus = [];
        foreach ($coupons as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ((array) ($row['category_slugs'] ?? []) as $slug) {
                $slug = trim((string) $slug);
                if ($slug !== '') {
                    $allCategorySlugs[$slug] = true;
                }
            }
            foreach ((array) ($row['variation_skus'] ?? []) as $sku) {
                $sku = trim((string) $sku);
                if ($sku !== '') {
                    $allVariationSkus[$sku] = true;
                }
            }
        }

        $categorySlugToId = $allCategorySlugs === []
            ? []
            : Category::where('business_id', $businessId)
                ->whereIn('slug', array_keys($allCategorySlugs))
                ->pluck('id', 'slug')
                ->all();

        $variationSkuToId = [];
        if ($allVariationSkus !== []) {
            foreach (array_chunk(array_keys($allVariationSkus), self::LOOKUP_CHUNK) as $chunk) {
                $rows = Variation::query()
                    ->join('products', 'variations.product_id', '=', 'products.id')
                    ->where('products.business_id', $businessId)
                    ->whereIn('variations.sub_sku', $chunk)
                    ->pluck('variations.id', 'variations.sub_sku');
                foreach ($rows as $sku => $id) {
                    $variationSkuToId[(string) $sku] = (int) $id;
                }
            }
        }

        $existing = Coupon::where('business_id', $businessId)
            ->whereIn('code', array_values(array_filter(array_map(
                fn ($row) => is_array($row) ? strtoupper(trim((string) ($row['code'] ?? ''))) : '',
                $coupons
            ))))
            ->get()
            ->keyBy(fn (Coupon $c) => (string) $c->code);

        $upserted = 0;
        foreach ($coupons as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($code === '') {
                continue;
            }

            $channel = (string) ($row['channel'] ?? Coupon::CHANNEL_STOREFRONT);
            if (! in_array($channel, [Coupon::CHANNEL_STOREFRONT, Coupon::CHANNEL_BOTH], true)) {
                $channel = Coupon::CHANNEL_STOREFRONT;
            }

            $coupon = $existing->get($code) ?? new Coupon();
            $coupon->fill([
                'business_id' => $businessId,
                'code' => $code,
                'name' => trim((string) ($row['name'] ?? $code)) ?: $code,
                'description' => $row['description'] ?? null,
                'type' => $row['type'] ?? Coupon::TYPE_PERCENT_ORDER,
                'discount_amount' => (float) ($row['discount_amount'] ?? 0),
                'max_discount_amount' => $row['max_discount_amount'] !== null && $row['max_discount_amount'] !== ''
                    ? (float) $row['max_discount_amount']
                    : null,
                'min_order_subtotal' => (float) ($row['min_order_subtotal'] ?? 0),
                'starts_at' => $row['starts_at'] ?? null,
                'ends_at' => $row['ends_at'] ?? null,
                'is_active' => ! empty($row['is_active']),
                'channel' => $channel,
                'max_uses_total' => isset($row['max_uses_total']) && $row['max_uses_total'] !== ''
                    ? (int) $row['max_uses_total']
                    : null,
                'max_uses_per_customer' => isset($row['max_uses_per_customer']) && $row['max_uses_per_customer'] !== ''
                    ? (int) $row['max_uses_per_customer']
                    : null,
                'first_order_only' => ! empty($row['first_order_only']),
                'exclude_sale_items' => ! empty($row['exclude_sale_items']),
                'stack_with_reward_points' => array_key_exists('stack_with_reward_points', $row)
                    ? ! empty($row['stack_with_reward_points'])
                    : true,
                'applies_to' => $row['applies_to'] ?? Coupon::APPLIES_ALL,
                'times_used' => 0,
            ]);
            $coupon->save();
            $existing[$code] = $coupon;

            $appliesTo = $coupon->applies_to;
            if ($appliesTo === Coupon::APPLIES_CATEGORIES) {
                $ids = [];
                foreach ((array) ($row['category_slugs'] ?? []) as $slug) {
                    $slug = (string) $slug;
                    if (isset($categorySlugToId[$slug])) {
                        $ids[] = (int) $categorySlugToId[$slug];
                    }
                }
                $coupon->categories()->sync($ids);
                $coupon->variations()->sync([]);
            } elseif ($appliesTo === Coupon::APPLIES_PRODUCTS) {
                $ids = [];
                foreach ((array) ($row['variation_skus'] ?? []) as $sku) {
                    $sku = (string) $sku;
                    if (isset($variationSkuToId[$sku])) {
                        $ids[] = (int) $variationSkuToId[$sku];
                    }
                }
                $coupon->variations()->sync($ids);
                $coupon->categories()->sync([]);
            } else {
                $coupon->categories()->sync([]);
                $coupon->variations()->sync([]);
            }

            $upserted++;
        }

        return ['upserted' => $upserted];
    }

    /**
     * @param  array<string, mixed>  $overlays
     * @param  array<string, int>  $classIdMap
     * @return array{categories: int, brands: int, products: int, variations: int}
     */
    private function importCatalogOverlays(int $businessId, array $overlays, array $classIdMap): array
    {
        $categoryRows = (array) ($overlays['categories'] ?? []);
        $brandRows = (array) ($overlays['brands'] ?? []);
        $productRows = (array) ($overlays['products'] ?? []);
        $variationRows = (array) ($overlays['variations'] ?? []);

        $categorySlugs = [];
        foreach ($categoryRows as $row) {
            if (is_array($row)) {
                $slug = trim((string) ($row['slug'] ?? ''));
                if ($slug !== '') {
                    $categorySlugs[$slug] = true;
                }
            }
        }
        $categories = $categorySlugs === []
            ? collect()
            : Category::where('business_id', $businessId)
                ->where('category_type', 'product')
                ->whereIn('slug', array_keys($categorySlugs))
                ->get()
                ->keyBy('slug');

        $catCount = 0;
        foreach ($categoryRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $slug = trim((string) ($row['slug'] ?? ''));
            /** @var Category|null $category */
            $category = $slug !== '' ? $categories->get($slug) : null;
            if (! $category) {
                continue;
            }
            foreach ([
                'image', 'shelf_banner', 'shelf_fg_image', 'shelf_heading', 'shelf_banner_kicker',
                'shelf_banner_text', 'shelf_button_text', 'shelf_banner_link', 'shelf_view_more_label',
            ] as $field) {
                if (array_key_exists($field, $row)) {
                    $category->{$field} = $row[$field];
                }
            }
            if (array_key_exists('show_on_homepage_shelf', $row)) {
                $category->show_on_homepage_shelf = ! empty($row['show_on_homepage_shelf']);
            }
            if (array_key_exists('homepage_shelf_sort', $row)) {
                $category->homepage_shelf_sort = (int) $row['homepage_shelf_sort'];
            }
            $category->save();
            $catCount++;
        }

        $brandSlugs = [];
        foreach ($brandRows as $row) {
            if (is_array($row)) {
                $slug = trim((string) ($row['slug'] ?? ''));
                if ($slug !== '') {
                    $brandSlugs[$slug] = true;
                }
            }
        }
        $brands = $brandSlugs === []
            ? collect()
            : Brands::where('business_id', $businessId)
                ->whereIn('slug', array_keys($brandSlugs))
                ->get()
                ->keyBy('slug');

        $brandCount = 0;
        foreach ($brandRows as $row) {
            if (! is_array($row) || ! array_key_exists('image', $row)) {
                continue;
            }
            $slug = trim((string) ($row['slug'] ?? ''));
            /** @var Brands|null $brand */
            $brand = $slug !== '' ? $brands->get($slug) : null;
            if (! $brand) {
                continue;
            }
            $brand->image = $row['image'];
            $brand->save();
            $brandCount++;
        }

        if ($classIdMap === []) {
            foreach (
                StorefrontShippingClass::where('business_id', $businessId)
                    ->toBase()
                    ->select(['id', 'slug', 'name'])
                    ->cursor() as $class
            ) {
                $slug = trim((string) ($class->slug ?? '')) ?: Str::slug((string) $class->name);
                if ($slug !== '') {
                    $classIdMap[$slug] = (int) $class->id;
                }
            }
        }

        $productSkus = [];
        foreach ($productRows as $row) {
            if (is_array($row)) {
                $sku = trim((string) ($row['sku'] ?? ''));
                if ($sku !== '') {
                    $productSkus[$sku] = true;
                }
            }
        }
        $products = collect();
        if ($productSkus !== []) {
            foreach (array_chunk(array_keys($productSkus), self::LOOKUP_CHUNK) as $chunk) {
                $products = $products->merge(
                    Product::where('business_id', $businessId)
                        ->whereIn('sku', $chunk)
                        ->get(['id', 'sku', 'is_storefront_featured', 'shipping_class_id'])
                );
            }
            $products = $products->keyBy('sku');
        }

        $productCount = 0;
        foreach ($productRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            /** @var Product|null $product */
            $product = $sku !== '' ? $products->get($sku) : null;
            if (! $product) {
                continue;
            }
            if (array_key_exists('is_storefront_featured', $row)) {
                $product->is_storefront_featured = ! empty($row['is_storefront_featured']);
            }
            if (array_key_exists('shipping_class_slug', $row)) {
                $slug = trim((string) ($row['shipping_class_slug'] ?? ''));
                $product->shipping_class_id = $slug !== '' ? ($classIdMap[$slug] ?? null) : null;
            }
            $product->save();
            $productCount++;
        }

        $variationCount = 0;
        $priceBySku = [];
        foreach ($variationRows as $row) {
            if (! is_array($row) || ! array_key_exists('storefront_sale_price_inc_tax', $row)) {
                continue;
            }
            $subSku = trim((string) ($row['sub_sku'] ?? ''));
            if ($subSku === '') {
                continue;
            }
            $priceBySku[$subSku] = $row['storefront_sale_price_inc_tax'];
        }
        foreach (array_chunk($priceBySku, self::LOOKUP_CHUNK, true) as $chunk) {
            foreach ($chunk as $subSku => $price) {
                $variationCount += (int) Variation::query()
                    ->join('products', 'variations.product_id', '=', 'products.id')
                    ->where('products.business_id', $businessId)
                    ->where('variations.sub_sku', $subSku)
                    ->update(['variations.storefront_sale_price_inc_tax' => $price]);
            }
        }

        return [
            'categories' => $catCount,
            'brands' => $brandCount,
            'products' => $productCount,
            'variations' => $variationCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array{products: int, categories: int, brands: int, variations: int}
     */
    private function importTranslations(int $businessId, array $translations): array
    {
        $productRows = (array) ($translations['products'] ?? []);
        $categoryRows = (array) ($translations['categories'] ?? []);
        $brandRows = (array) ($translations['brands'] ?? []);
        $variationRows = (array) ($translations['variations'] ?? []);

        $skuMap = $this->pluckMap(
            Product::where('business_id', $businessId),
            'sku',
            'id',
            array_values(array_filter(array_map(
                fn ($row) => is_array($row) ? trim((string) ($row['product_sku'] ?? '')) : '',
                $productRows
            )))
        );

        $productCount = 0;
        foreach ($productRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['product_sku'] ?? ''));
            $locale = trim((string) ($row['locale'] ?? ''));
            if ($sku === '' || $locale === '' || ! isset($skuMap[$sku])) {
                continue;
            }
            ProductTranslation::updateOrCreate(
                ['product_id' => $skuMap[$sku], 'locale' => $locale],
                [
                    'name' => (string) ($row['name'] ?? ''),
                    'product_description' => $row['product_description'] ?? null,
                    'slug' => $row['slug'] ?? null,
                ]
            );
            $productCount++;
        }

        $categoryMap = $this->pluckMap(
            Category::where('business_id', $businessId),
            'slug',
            'id',
            array_values(array_filter(array_map(
                fn ($row) => is_array($row) ? trim((string) ($row['category_slug'] ?? '')) : '',
                $categoryRows
            )))
        );

        $categoryCount = 0;
        foreach ($categoryRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $slug = trim((string) ($row['category_slug'] ?? ''));
            $locale = trim((string) ($row['locale'] ?? ''));
            if ($slug === '' || $locale === '' || ! isset($categoryMap[$slug])) {
                continue;
            }
            CategoryTranslation::updateOrCreate(
                ['category_id' => $categoryMap[$slug], 'locale' => $locale],
                [
                    'name' => (string) ($row['name'] ?? ''),
                    'slug' => $row['slug'] ?? null,
                ]
            );
            $categoryCount++;
        }

        $brandMap = $this->pluckMap(
            Brands::where('business_id', $businessId),
            'slug',
            'id',
            array_values(array_filter(array_map(
                fn ($row) => is_array($row) ? trim((string) ($row['brand_slug'] ?? '')) : '',
                $brandRows
            )))
        );

        $brandCount = 0;
        foreach ($brandRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $slug = trim((string) ($row['brand_slug'] ?? ''));
            $locale = trim((string) ($row['locale'] ?? ''));
            if ($slug === '' || $locale === '' || ! isset($brandMap[$slug])) {
                continue;
            }
            BrandTranslation::updateOrCreate(
                ['brand_id' => $brandMap[$slug], 'locale' => $locale],
                ['name' => (string) ($row['name'] ?? '')]
            );
            $brandCount++;
        }

        $subSkus = array_values(array_unique(array_filter(array_map(
            fn ($row) => is_array($row) ? trim((string) ($row['sub_sku'] ?? '')) : '',
            $variationRows
        ))));
        $variationMap = [];
        foreach (array_chunk($subSkus, self::LOOKUP_CHUNK) as $chunk) {
            $rows = Variation::query()
                ->join('products', 'variations.product_id', '=', 'products.id')
                ->where('products.business_id', $businessId)
                ->whereIn('variations.sub_sku', $chunk)
                ->pluck('variations.id', 'variations.sub_sku');
            foreach ($rows as $sku => $id) {
                $variationMap[(string) $sku] = (int) $id;
            }
        }

        $variationCount = 0;
        foreach ($variationRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $subSku = trim((string) ($row['sub_sku'] ?? ''));
            $locale = trim((string) ($row['locale'] ?? ''));
            if ($subSku === '' || $locale === '' || ! isset($variationMap[$subSku])) {
                continue;
            }
            VariationTranslation::updateOrCreate(
                ['variation_id' => $variationMap[$subSku], 'locale' => $locale],
                ['name' => (string) ($row['name'] ?? '')]
            );
            $variationCount++;
        }

        return [
            'products' => $productCount,
            'categories' => $categoryCount,
            'brands' => $brandCount,
            'variations' => $variationCount,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    private function pluckMap($query, string $keyColumn, string $valueColumn, array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys)));
        if ($keys === []) {
            return [];
        }

        $map = [];
        foreach (array_chunk($keys, self::LOOKUP_CHUNK) as $chunk) {
            foreach ($query->clone()->whereIn($keyColumn, $chunk)->pluck($valueColumn, $keyColumn) as $key => $value) {
                $map[(string) $key] = (int) $value;
            }
        }

        return $map;
    }

    private function disableQueryLog(): void
    {
        try {
            DB::connection()->disableQueryLog();
        } catch (\Throwable) {
            // Ignore when connection is unavailable in early boot.
        }
    }
}
