<?php

namespace App\Services\Storefront\Homepage;

use App\Services\Storefront\CatalogService;
use App\Services\Storefront\StorefrontMediaLibraryService;
use Illuminate\Support\Str;

/**
 * Normalize, seed, and present homepage_sections for admin + public API.
 */
class HomepageSectionService
{
    private const UPLOAD_DIR = 'uploads/storefront_homepage';

    private const WP = 'https://gamesspoteg.com/wp-content/uploads';

    public function __construct(
        private SectionTypeRegistry $registry,
        private CatalogService $catalog
    ) {
    }

    /**
     * Default homepage stack matching the pre-builder Qwik order + demo media.
     *
     * @return array<int, array{id: string, type: string, enabled: bool, settings: array<string, mixed>}>
     */
    public function defaultSections(): array
    {
        return [
            [
                'id' => 'sec_hero',
                'type' => 'hero_slider',
                'enabled' => true,
                'settings' => [
                    'slides' => [
                        [
                            'id' => 'slide-1',
                            'image' => null,
                            'url' => self::WP.'/2024/03/bg-slider-1.png',
                            'href' => '/products',
                            'kicker' => ['en' => 'Witness Play Unleashed™', 'ar' => 'Witness Play Unleashed™'],
                            'title' => [
                                'en' => 'PS5 Ghost of Yotei Gold Limited Edition Bundle',
                                'ar' => 'PS5 Ghost of Yotei Gold Limited Edition Bundle',
                            ],
                        ],
                        [
                            'id' => 'slide-2',
                            'image' => null,
                            'url' => self::WP.'/2024/03/home1-slide2.png',
                            'href' => '/products',
                            'kicker' => ['en' => 'wireless controller', 'ar' => 'wireless controller'],
                            'title' => [
                                'en' => 'DualSense® 007 First Light™',
                                'ar' => 'DualSense® 007 First Light™',
                            ],
                        ],
                        [
                            'id' => 'slide-3',
                            'image' => null,
                            'url' => self::WP.'/2024/03/home1-slide3.png',
                            'href' => '/products',
                            'kicker' => ['en' => 'PULSE™ wireless headset', 'ar' => 'PULSE™ wireless headset'],
                            'title' => [
                                'en' => 'A new era in gaming audio',
                                'ar' => 'A new era in gaming audio',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'sec_promo_tiles',
                'type' => 'promo_tiles',
                'enabled' => true,
                'settings' => [
                    'tiles' => [
                        [
                            'id' => 'promo-main',
                            'image' => null,
                            'url' => self::WP.'/2026/06/26517668.jpg.webp',
                            'href' => '/products',
                            'label' => ['en' => '007 First Light', 'ar' => '007 First Light'],
                        ],
                        [
                            'id' => 'promo-2',
                            'image' => null,
                            'url' => self::WP.'/2025/10/IMG_2392.jpeg',
                            'href' => '/products',
                            'label' => ['en' => 'Shop now', 'ar' => 'تسوق الآن'],
                        ],
                        [
                            'id' => 'promo-3',
                            'image' => null,
                            'url' => self::WP.'/2025/10/IMG_2393-scaled.jpeg',
                            'href' => '/products',
                            'label' => ['en' => 'Shop now', 'ar' => 'تسوق الآن'],
                        ],
                        [
                            'id' => 'promo-4',
                            'image' => null,
                            'url' => self::WP.'/2026/06/thumb-1920-1397346-1.jpg',
                            'href' => '/products',
                            'label' => ['en' => 'Shop now', 'ar' => 'تسوق الآن'],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'sec_video',
                'type' => 'video',
                'enabled' => true,
                'settings' => [
                    'source' => 'self',
                    'url' => self::WP.'/2026/06/Grand-Theft-Auto-VI-Trailer-2.mp4',
                    'poster' => self::WP.'/2026/06/poster_full.0az_iud2g3y4j.jpg',
                    'title' => ['en' => '', 'ar' => ''],
                ],
            ],
            [
                'id' => 'sec_promo_banners',
                'type' => 'promo_banners',
                'enabled' => true,
                'settings' => ['max' => 12],
            ],
            [
                'id' => 'sec_featured',
                'type' => 'featured_products',
                'enabled' => true,
                'settings' => ['per_page' => 8],
            ],
            [
                'id' => 'sec_top_categories',
                'type' => 'top_categories',
                'enabled' => true,
                'settings' => ['limit' => 8],
            ],
            [
                'id' => 'sec_shelves',
                'type' => 'category_shelves',
                'enabled' => true,
                'settings' => ['limit' => 6, 'products_per_shelf' => 6],
            ],
            [
                'id' => 'sec_brands',
                'type' => 'brand_slider',
                'enabled' => true,
                'settings' => ['limit' => 24],
            ],
            [
                'id' => 'sec_bestsellers',
                'type' => 'bestsellers',
                'enabled' => true,
                'settings' => ['per_page' => 6, 'in_stock_only' => true],
            ],
            [
                'id' => 'sec_recent',
                'type' => 'recently_viewed',
                'enabled' => true,
                'settings' => ['limit' => 8],
            ],
        ];
    }

    /**
     * Ensure settings always have a usable homepage_sections list.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function ensureSections(array $settings): array
    {
        $sections = $settings['homepage_sections'] ?? null;
        if (! is_array($sections) || $sections === []) {
            $settings['homepage_sections'] = $this->defaultSections();
        } else {
            $settings['homepage_sections'] = $this->normalizeSections($sections);
        }

        return $settings;
    }

    /**
     * @param  mixed  $sections
     * @return array<int, array{id: string, type: string, enabled: bool, settings: array<string, mixed>}>
     */
    public function normalizeSections($sections, ?int $businessId = null): array
    {
        if (! is_array($sections)) {
            return $this->defaultSections();
        }

        $counts = [];
        $normalized = [];

        foreach ($sections as $row) {
            if (! is_array($row)) {
                continue;
            }

            $type = trim((string) ($row['type'] ?? ''));
            if (! $this->registry->has($type)) {
                continue;
            }

            $meta = $this->registry->get($type);
            $max = $meta['max_instances'] ?? null;
            $counts[$type] = ($counts[$type] ?? 0) + 1;
            if ($max !== null && $counts[$type] > $max) {
                continue;
            }

            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                $id = 'sec_'.Str::lower(Str::random(8));
            }

            $normalized[] = [
                'id' => mb_substr($id, 0, 40),
                'type' => $type,
                'enabled' => filter_var($row['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'layout_width' => $this->normalizeLayoutWidth($row['layout_width'] ?? null),
                'settings' => $this->normalizeSettings(
                    $type,
                    is_array($row['settings'] ?? null) ? $row['settings'] : [],
                    $businessId
                ),
            ];
        }

        return $normalized === [] ? $this->defaultSections() : array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function normalizeSettings(string $type, array $settings, ?int $businessId = null): array
    {
        $defaults = $this->registry->get($type)['default_settings'] ?? [];

        return match ($type) {
            'hero_slider' => [
                'slides' => $this->normalizeSlides($settings['slides'] ?? []),
            ],
            'promo_tiles' => [
                'tiles' => $this->normalizeTiles($settings['tiles'] ?? []),
            ],
            'video' => $this->normalizeVideo($settings),
            'trust_badges' => [
                'items' => $this->normalizeTrustBadgeItems($settings['items'] ?? [], $businessId),
            ],
            'promo_banners' => [
                'max' => max(1, min(24, (int) ($settings['max'] ?? 12))),
            ],
            'promo_banner' => $this->normalizePromoBanner($settings),
            'featured_products' => [
                'per_page' => max(1, min(24, (int) ($settings['per_page'] ?? 8))),
            ],
            'top_categories' => [
                'limit' => max(1, min(24, (int) ($settings['limit'] ?? 8))),
            ],
            'category_shelves' => [
                'limit' => max(1, min(12, (int) ($settings['limit'] ?? 6))),
                'products_per_shelf' => max(1, min(24, (int) ($settings['products_per_shelf'] ?? 6))),
            ],
            'category_shelf' => [
                'category_id' => max(0, (int) ($settings['category_id'] ?? 0)) ?: null,
                'products_per_shelf' => max(1, min(24, (int) ($settings['products_per_shelf'] ?? 6))),
            ],
            'brand_slider' => [
                'limit' => max(1, min(48, (int) ($settings['limit'] ?? 24))),
            ],
            'bestsellers' => [
                'per_page' => max(1, min(24, (int) ($settings['per_page'] ?? 6))),
                'in_stock_only' => filter_var($settings['in_stock_only'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'style' => $this->normalizeBestsellersStyle($settings['style'] ?? null),
            ],
            'recently_viewed' => [
                'limit' => max(1, min(24, (int) ($settings['limit'] ?? 8))),
            ],
            default => array_replace_recursive($defaults, $settings),
        };
    }

    /**
     * Public API shape: enabled sections only, localized media URLs.
     *
     * @param  array<int, array{id: string, type: string, enabled: bool, settings: array<string, mixed>}>  $sections
     * @return array<int, array{id: string, type: string, settings: array<string, mixed>}>
     */
    public function presentForApi(array $sections, string $locale, int $businessId = 0): array
    {
        $out = [];
        foreach ($sections as $section) {
            if (! ($section['enabled'] ?? false)) {
                continue;
            }
            $type = (string) ($section['type'] ?? '');
            if (! $this->registry->has($type)) {
                continue;
            }

            $settings = $this->presentSettings($type, $section['settings'] ?? [], $locale);

            if ($type === 'category_shelf') {
                $categoryId = (int) ($settings['category_id'] ?? 0);
                if ($categoryId < 1 || $businessId < 1) {
                    continue;
                }
                $shelf = $this->catalog->getCategoryShelfById($businessId, $categoryId, $locale);
                if ($shelf === null) {
                    continue;
                }
                $settings['shelf'] = $shelf;
            }

            if ($type === 'promo_banner' && empty($settings['has_content'])) {
                continue;
            }
            if ($type === 'promo_banner') {
                unset($settings['has_content']);
            }

            if ($type === 'video') {
                $source = (string) ($settings['source'] ?? 'self');
                $hasVideo = $source === 'self'
                    ? trim((string) ($settings['url'] ?? '')) !== ''
                    : ! empty($settings['embed_url']);
                if (! $hasVideo) {
                    continue;
                }
            }

            if ($type === 'trust_badges') {
                $items = $settings['items'] ?? [];
                if (! is_array($items) || $items === []) {
                    continue;
                }
            }

            $out[] = [
                'id' => $section['id'],
                'type' => $type,
                'layout_width' => $this->normalizeLayoutWidth($section['layout_width'] ?? null),
                'settings' => $settings,
            ];
        }

        return $out;
    }

    /**
     * Admin builder payload (full settings, EN/AR maps intact).
     *
     * @param  array<int, array{id: string, type: string, enabled: bool, settings: array<string, mixed>}>  $sections
     * @return array<int, array{id: string, type: string, enabled: bool, settings: array<string, mixed>}>
     */
    public function presentForAdmin(array $sections): array
    {
        $out = [];
        foreach ($sections as $section) {
            $type = (string) ($section['type'] ?? '');
            if (! $this->registry->has($type)) {
                continue;
            }
            $settings = $section['settings'] ?? [];
            if ($type === 'hero_slider') {
                $settings['slides'] = array_map(fn ($s) => $this->withMediaUrl($s), $settings['slides'] ?? []);
            }
            if ($type === 'promo_tiles') {
                $settings['tiles'] = array_map(fn ($t) => $this->withMediaUrl($t), $settings['tiles'] ?? []);
            }
            if ($type === 'trust_badges') {
                $settings['items'] = array_map(
                    function ($item) {
                        $row = $this->withMediaUrl(is_array($item) ? $item : []);
                        // Never rehydrate SVG file contents into PHP (OOM risk on large files).
                        unset($row['svg_markup'], $row['svg_markup_b64']);
                        $kind = $this->normalizeIconKind($row['icon_kind'] ?? null);
                        if ($kind === 'svg' && empty($row['image']) && empty($row['url'])) {
                            $kind = 'image';
                        }
                        // Prefer file extension when present.
                        $path = (string) ($row['image'] ?? '');
                        if ($path !== '' && preg_match('/\.svg$/i', $path)) {
                            $kind = 'svg';
                        }
                        if (! isset($row['title']) || ! is_array($row['title'])) {
                            $row['title'] = $this->localeMap($row['title'] ?? null, 120);
                        }
                        if (! isset($row['description']) || ! is_array($row['description'])) {
                            $row['description'] = $this->localeMap($row['description'] ?? null, 240);
                        }
                        $row['icon_kind'] = $kind;
                        if (! isset($row['icon_color'])) {
                            $row['icon_color'] = $this->cssColor($row['icon_color'] ?? null, '#f5a623');
                        }

                        return $row;
                    },
                    $settings['items'] ?? []
                );
            }
            if ($type === 'promo_banner') {
                $settings = $this->normalizePromoBanner(is_array($settings) ? $settings : []);
                $settings['logo'] = $this->withMediaUrl($settings['logo']);
                $settings['image'] = $this->withMediaUrl($settings['image']);
            }
            if ($type === 'video') {
                $settings = $this->normalizeVideo(is_array($settings) ? $settings : []);
            }
            if ($type === 'bestsellers') {
                $settings = array_merge(
                    $this->registry->get('bestsellers')['default_settings'] ?? [],
                    is_array($settings) ? $settings : []
                );
                $settings['style'] = $this->normalizeBestsellersStyle($settings['style'] ?? null);
                $settings['per_page'] = max(1, min(24, (int) ($settings['per_page'] ?? 6)));
                $settings['in_stock_only'] = filter_var($settings['in_stock_only'] ?? true, FILTER_VALIDATE_BOOLEAN);
            }
            $out[] = [
                'id' => $section['id'],
                'type' => $type,
                'enabled' => (bool) ($section['enabled'] ?? true),
                'layout_width' => $this->normalizeLayoutWidth($section['layout_width'] ?? null),
                'settings' => $settings,
            ];
        }

        return $out;
    }

    public function mediaPublicUrl(?string $image, ?string $url): ?string
    {
        $image = trim((string) $image);
        if ($image !== '') {
            $rel = $this->resolveUploadRelativePath($image);
            if ($rel !== null) {
                return asset('uploads/'.$rel);
            }
        }

        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return url($url);
        }

        return asset($url);
    }

    /**
     * Resolve a stored image key to a path under public/uploads.
     * Legacy: bare filename → storefront_homepage/{file}
     * Library: storefront_library/{business_id}/{file}
     */
    public function resolveUploadRelativePath(string $image): ?string
    {
        $image = str_replace('\\', '/', trim($image));
        $image = ltrim($image, '/');
        if ($image === '' || str_contains($image, '..')) {
            return null;
        }

        if (str_contains($image, '/')) {
            if (
                str_starts_with($image, StorefrontMediaLibraryService::DIR.'/')
                || str_starts_with($image, 'storefront_homepage/')
            ) {
                return $image;
            }

            return null;
        }

        return 'storefront_homepage/'.$image;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function presentSettings(string $type, array $settings, string $locale): array
    {
        return match ($type) {
            'hero_slider' => [
                'slides' => array_values(array_filter(array_map(function ($slide) use ($locale) {
                    $imageUrl = $this->mediaPublicUrl($slide['image'] ?? null, $slide['url'] ?? null);
                    if ($imageUrl === null) {
                        return null;
                    }

                    return [
                        'id' => $slide['id'],
                        'image_url' => $imageUrl,
                        'href' => $slide['href'] ?? '/products',
                        'kicker' => $this->pickLocale($slide['kicker'] ?? [], $locale),
                        'title' => $this->pickLocale($slide['title'] ?? [], $locale),
                    ];
                }, $settings['slides'] ?? []))),
            ],
            'promo_tiles' => [
                'tiles' => array_values(array_filter(array_map(function ($tile) use ($locale) {
                    $imageUrl = $this->mediaPublicUrl($tile['image'] ?? null, $tile['url'] ?? null);
                    if ($imageUrl === null) {
                        return null;
                    }

                    return [
                        'id' => $tile['id'],
                        'image_url' => $imageUrl,
                        'href' => $tile['href'] ?? '/products',
                        'label' => $this->pickLocale($tile['label'] ?? [], $locale),
                    ];
                }, $settings['tiles'] ?? []))),
            ],
            'video' => $this->presentVideo($settings, $locale),
            'trust_badges' => [
                'items' => array_values(array_filter(array_map(function ($item) use ($locale) {
                    if (! is_array($item)) {
                        return null;
                    }
                    $title = $this->pickLocale($item['title'] ?? [], $locale);
                    $description = $this->pickLocale($item['description'] ?? [], $locale);
                    if ($title === '' && $description === '') {
                        return null;
                    }
                    $iconUrl = $this->mediaPublicUrl($item['image'] ?? null, $item['url'] ?? null);
                    $path = (string) ($item['image'] ?? '');
                    $kind = preg_match('/\.svg$/i', $path) ? 'svg' : 'image';

                    return [
                        'id' => (string) ($item['id'] ?? ''),
                        'icon_kind' => $kind,
                        'icon_url' => $iconUrl,
                        'icon_color' => $this->cssColor($item['icon_color'] ?? null, '#f5a623'),
                        'title' => $title,
                        'description' => $description,
                    ];
                }, $settings['items'] ?? []))),
            ],
            'category_shelf' => [
                'category_id' => max(0, (int) ($settings['category_id'] ?? 0)) ?: null,
                'products_per_shelf' => max(1, min(24, (int) ($settings['products_per_shelf'] ?? 6))),
            ],
            'promo_banner' => $this->presentPromoBanner($settings, $locale),
            default => $settings,
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function presentPromoBanner(array $settings, string $locale): array
    {
        $logo = is_array($settings['logo'] ?? null) ? $settings['logo'] : [];
        $image = is_array($settings['image'] ?? null) ? $settings['image'] : [];
        $button = is_array($settings['button'] ?? null) ? $settings['button'] : [];
        $imagePosition = is_array($image['position'] ?? null) ? $image['position'] : [];
        $buttonPosition = is_array($button['position'] ?? null) ? $button['position'] : [];

        $logoUrl = $this->mediaPublicUrl($logo['image'] ?? null, $logo['url'] ?? null);
        $imageUrl = $this->mediaPublicUrl($image['image'] ?? null, $image['url'] ?? null);
        $topTitle = $this->pickLocale($settings['top_title'] ?? [], $locale);
        $mainTitle = $this->pickLocale($settings['main_title'] ?? [], $locale);
        $buttonLabel = $this->pickLocale($button['label'] ?? [], $locale);

        return [
            'has_content' => $logoUrl !== null || $imageUrl !== null || $topTitle !== '' || $mainTitle !== '',
            'logo_url' => $logoUrl,
            'top_title' => $topTitle,
            'main_title' => $mainTitle,
            'top_title_color' => $this->cssColor($settings['top_title_color'] ?? null, '#111111'),
            'main_title_color' => $this->cssColor($settings['main_title_color'] ?? null, '#111111'),
            'background_color' => $this->cssColor($settings['background_color'] ?? null, '#f5a623'),
            'border_radius' => max(0, min(64, (int) ($settings['border_radius'] ?? 16))),
            'border_color' => $this->cssColor($settings['border_color'] ?? null, '#000000'),
            'border_thickness' => max(0, min(24, (int) ($settings['border_thickness'] ?? 0))),
            'min_height' => max(80, min(640, (int) ($settings['min_height'] ?? 180))),
            'image_url' => $imageUrl,
            'image_position' => $this->normalizePosition($imagePosition, [
                'top' => '-12%',
                'right' => '2%',
                'bottom' => 'auto',
                'left' => 'auto',
                'width' => '42%',
            ]),
            'button' => [
                'label' => $buttonLabel !== '' ? $buttonLabel : 'Shop Now',
                'link' => mb_substr(trim((string) ($button['link'] ?? '')), 0, 500),
                'background_color' => $this->cssColor($button['background_color'] ?? null, '#ffffff'),
                'text_color' => $this->cssColor($button['text_color'] ?? null, '#111111'),
                'border_radius' => max(0, min(64, (int) ($button['border_radius'] ?? 4))),
                'show_arrow' => filter_var($button['show_arrow'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'arrow_color' => $this->cssColor($button['arrow_color'] ?? null, '#f5c518'),
                'position' => $this->normalizePosition($buttonPosition, [
                    'top' => 'auto',
                    'right' => '5%',
                    'bottom' => '18%',
                    'left' => 'auto',
                    'width' => 'auto',
                ]),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function normalizePromoBanner(array $settings): array
    {
        // Migrate legacy flat image/url/title/link shape into compositional fields.
        if (! isset($settings['logo']) && ! is_array($settings['image'] ?? null) && (isset($settings['image']) || isset($settings['url']) || isset($settings['title']))) {
            $legacyImage = trim((string) ($settings['image'] ?? ''));
            $legacyUrl = trim((string) ($settings['url'] ?? ''));
            $legacyTitle = $settings['title'] ?? null;
            $legacyLink = trim((string) ($settings['link'] ?? ''));
            $settings['image'] = [
                'image' => $legacyImage !== '' ? $legacyImage : null,
                'url' => $legacyImage === '' ? $legacyUrl : '',
                'position' => [
                    'top' => '-12%',
                    'right' => '2%',
                    'bottom' => 'auto',
                    'left' => 'auto',
                    'width' => '42%',
                ],
            ];
            if (! isset($settings['main_title']) && $legacyTitle !== null) {
                $settings['main_title'] = $legacyTitle;
            }
            if (! isset($settings['button']) && $legacyLink !== '') {
                $settings['button'] = [
                    'label' => ['en' => 'Shop Now', 'ar' => 'تسوق الآن'],
                    'link' => $legacyLink,
                ];
            }
        }

        $logo = is_array($settings['logo'] ?? null) ? $settings['logo'] : [];
        $image = is_array($settings['image'] ?? null) ? $settings['image'] : [];
        $button = is_array($settings['button'] ?? null) ? $settings['button'] : [];

        return [
            'logo' => $this->normalizeMediaRow($logo),
            'top_title' => $this->localeMap($settings['top_title'] ?? null, 160),
            'main_title' => $this->localeMap($settings['main_title'] ?? null, 220),
            'top_title_color' => $this->cssColor($settings['top_title_color'] ?? null, '#111111'),
            'main_title_color' => $this->cssColor($settings['main_title_color'] ?? null, '#111111'),
            'background_color' => $this->cssColor($settings['background_color'] ?? null, '#f5a623'),
            'border_radius' => max(0, min(64, (int) ($settings['border_radius'] ?? 16))),
            'border_color' => $this->cssColor($settings['border_color'] ?? null, '#000000'),
            'border_thickness' => max(0, min(24, (int) ($settings['border_thickness'] ?? 0))),
            'min_height' => max(80, min(640, (int) ($settings['min_height'] ?? 180))),
            'image' => array_merge($this->normalizeMediaRow($image), [
                'position' => $this->normalizePosition($image['position'] ?? null, [
                    'top' => '-12%',
                    'right' => '2%',
                    'bottom' => 'auto',
                    'left' => 'auto',
                    'width' => '42%',
                ]),
            ]),
            'button' => [
                'label' => $this->localeMap($button['label'] ?? ['en' => 'Shop Now', 'ar' => 'تسوق الآن'], 80),
                'link' => mb_substr(trim((string) ($button['link'] ?? '')), 0, 500),
                'background_color' => $this->cssColor($button['background_color'] ?? null, '#ffffff'),
                'text_color' => $this->cssColor($button['text_color'] ?? null, '#111111'),
                'border_radius' => max(0, min(64, (int) ($button['border_radius'] ?? 4))),
                'show_arrow' => filter_var($button['show_arrow'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'arrow_color' => $this->cssColor($button['arrow_color'] ?? null, '#f5c518'),
                'position' => $this->normalizePosition($button['position'] ?? null, [
                    'top' => 'auto',
                    'right' => '5%',
                    'bottom' => '18%',
                    'left' => 'auto',
                    'width' => 'auto',
                ]),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{image: string|null, url: string}
     */
    private function normalizeMediaRow(array $row): array
    {
        $image = trim((string) ($row['image'] ?? ''));
        if ($image !== '') {
            // Accept legacy filenames and library-relative paths only.
            $rel = $this->resolveUploadRelativePath($image);
            $image = $rel ?? '';
        }
        $url = trim((string) ($row['url'] ?? ''));

        return [
            'image' => $image !== '' ? $image : null,
            'url' => $image === '' ? mb_substr($url, 0, 1000) : '',
        ];
    }

    /**
     * @param  mixed  $position
     * @param  array{top: string, right: string, bottom: string, left: string, width: string}  $defaults
     * @return array{top: string, right: string, bottom: string, left: string, width: string}
     */
    private function normalizePosition($position, array $defaults): array
    {
        $row = is_array($position) ? $position : [];

        return [
            'top' => $this->cssLength($row['top'] ?? null, $defaults['top']),
            'right' => $this->cssLength($row['right'] ?? null, $defaults['right']),
            'bottom' => $this->cssLength($row['bottom'] ?? null, $defaults['bottom']),
            'left' => $this->cssLength($row['left'] ?? null, $defaults['left']),
            'width' => $this->cssLength($row['width'] ?? null, $defaults['width']),
        ];
    }

    private function cssColor(mixed $value, string $fallback): string
    {
        $v = trim((string) $value);
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $v)) {
            return $v;
        }
        if (preg_match('/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/i', $v)) {
            return $v;
        }

        return $fallback;
    }

    private function cssLength(mixed $value, string $fallback): string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return $fallback;
        }
        if (strcasecmp($v, 'auto') === 0) {
            return 'auto';
        }
        if (preg_match('/^-?\d+(\.\d+)?(px|%|rem|em|vh|vw)$/i', $v)) {
            return $v;
        }
        if (preg_match('/^-?\d+(\.\d+)?$/', $v)) {
            return $v.'px';
        }

        return $fallback;
    }

    /**
     * @param  mixed  $slides
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSlides($slides): array
    {
        if (! is_array($slides)) {
            return [];
        }

        $out = [];
        foreach (array_slice($slides, 0, 12) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $image = trim((string) ($row['image'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            if ($image === '' && $url === '') {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                $id = 'slide_'.Str::lower(Str::random(6));
            }
            $out[] = [
                'id' => mb_substr($id, 0, 40),
                'image' => $image !== '' ? $image : null,
                'url' => $image === '' ? mb_substr($url, 0, 1000) : '',
                'href' => mb_substr(trim((string) ($row['href'] ?? '/products')), 0, 500) ?: '/products',
                'kicker' => $this->localeMap($row['kicker'] ?? null, 120),
                'title' => $this->localeMap($row['title'] ?? null, 200),
            ];
        }

        return $out;
    }

    /**
     * @param  mixed  $tiles
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTiles($tiles): array
    {
        if (! is_array($tiles)) {
            return [];
        }

        $out = [];
        foreach (array_slice($tiles, 0, 12) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $image = trim((string) ($row['image'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            if ($image === '' && $url === '') {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                $id = 'tile_'.Str::lower(Str::random(6));
            }
            $out[] = [
                'id' => mb_substr($id, 0, 40),
                'image' => $image !== '' ? $image : null,
                'url' => $image === '' ? mb_substr($url, 0, 1000) : '',
                'href' => mb_substr(trim((string) ($row['href'] ?? '/products')), 0, 500) ?: '/products',
                'label' => $this->localeMap($row['label'] ?? null, 80),
            ];
        }

        return $out;
    }

    /**
     * @param  mixed  $value
     * @return array{en: string, ar: string}
     */
    private function localeMap($value, int $max): array
    {
        if (is_string($value)) {
            return ['en' => mb_substr(trim($value), 0, $max), 'ar' => ''];
        }
        if (! is_array($value)) {
            return ['en' => '', 'ar' => ''];
        }

        return [
            'en' => mb_substr(trim((string) ($value['en'] ?? '')), 0, $max),
            'ar' => mb_substr(trim((string) ($value['ar'] ?? '')), 0, $max),
        ];
    }

    /**
     * @param  mixed  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTrustBadgeItems($items, ?int $businessId = null): array
    {
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach (array_slice($items, 0, 8) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = $this->localeMap($row['title'] ?? null, 120);
            $description = $this->localeMap($row['description'] ?? null, 240);
            $media = $this->normalizeMediaRow($row);
            $kind = $this->normalizeIconKind($row['icon_kind'] ?? null);
            $path = (string) ($media['image'] ?? '');
            if ($path !== '' && preg_match('/\.svg$/i', $path)) {
                $kind = 'svg';
            } elseif ($kind === 'svg' && $path === '' && $media['url'] === '') {
                // Inline SVG paste is no longer supported — require a library file/URL.
                $kind = 'image';
            }

            if (
                $title['en'] === '' && $title['ar'] === ''
                && $description['en'] === '' && $description['ar'] === ''
                && $media['image'] === null && $media['url'] === ''
            ) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                $id = 'badge_'.Str::lower(Str::random(6));
            }

            $out[] = [
                'id' => mb_substr($id, 0, 40),
                'icon_kind' => $kind,
                'icon_color' => $this->cssColor($row['icon_color'] ?? null, '#f5a623'),
                'image' => $media['image'],
                'url' => $media['url'],
                'title' => $title,
                'description' => $description,
            ];
        }

        return $out;
    }

    private function normalizeLayoutWidth(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return $value === 'full' ? 'full' : 'boxed';
    }

    private function normalizeIconKind(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return $value === 'svg' ? 'svg' : 'image';
    }

    private function normalizeBestsellersStyle(mixed $style): string
    {
        $style = strtolower(trim((string) $style));

        return in_array($style, ['grid', 'horizontal'], true) ? $style : 'grid';
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{source: string, url: string, poster: string, title: array{en: string, ar: string}}
     */
    private function normalizeVideo(array $settings): array
    {
        $url = mb_substr(trim((string) ($settings['url'] ?? '')), 0, 1000);
        $source = $this->normalizeVideoSource($settings['source'] ?? null, $url);

        return [
            'source' => $source,
            'url' => $url,
            'poster' => $source === 'self' ? mb_substr(trim((string) ($settings['poster'] ?? '')), 0, 1000) : '',
            'title' => $this->localeMap($settings['title'] ?? null, 120),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{source: string, url: string, poster: string, title: string, embed_url: string|null}
     */
    private function presentVideo(array $settings, string $locale): array
    {
        $url = trim((string) ($settings['url'] ?? ''));
        $source = $this->normalizeVideoSource($settings['source'] ?? null, $url);
        $embedUrl = null;
        if ($source === 'youtube') {
            $embedUrl = $this->youtubeEmbedUrl($url);
        } elseif ($source === 'vimeo') {
            $embedUrl = $this->vimeoEmbedUrl($url);
        }

        return [
            'source' => $source,
            'url' => $url,
            'poster' => $source === 'self' ? trim((string) ($settings['poster'] ?? '')) : '',
            'title' => $this->pickLocale($settings['title'] ?? [], $locale),
            'embed_url' => $embedUrl,
        ];
    }

    private function normalizeVideoSource(mixed $source, string $url): string
    {
        $source = strtolower(trim((string) $source));
        if (in_array($source, ['youtube', 'vimeo', 'self'], true)) {
            return $source;
        }

        if ($this->youtubeEmbedUrl($url) !== null) {
            return 'youtube';
        }
        if ($this->vimeoEmbedUrl($url) !== null) {
            return 'vimeo';
        }

        return 'self';
    }

    private function youtubeEmbedUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $id = null;
        if (preg_match('~(?:youtube\.com/watch\?(?:[^#]*&)?v=|youtu\.be/|youtube\.com/embed/|youtube\.com/shorts/)([A-Za-z0-9_-]{6,})~i', $url, $m)) {
            $id = $m[1];
        } elseif (preg_match('~^[A-Za-z0-9_-]{6,}$~', $url)) {
            $id = $url;
        }

        if ($id === null) {
            return null;
        }

        return 'https://www.youtube-nocookie.com/embed/'.$id;
    }

    private function vimeoEmbedUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $id = null;
        if (preg_match('~(?:player\.)?vimeo\.com/(?:video/)?(\d+)~i', $url, $m)) {
            $id = $m[1];
        } elseif (preg_match('~^\d+$~', $url)) {
            $id = $url;
        }

        if ($id === null) {
            return null;
        }

        return 'https://player.vimeo.com/video/'.$id;
    }

    /**
     * @param  array{en?: string, ar?: string}|string  $map
     */
    private function pickLocale($map, string $locale): string
    {
        if (is_string($map)) {
            return $map;
        }
        if (! is_array($map)) {
            return '';
        }
        $preferred = trim((string) ($map[$locale] ?? ''));
        if ($preferred !== '') {
            return $preferred;
        }

        return trim((string) ($map['en'] ?? $map['ar'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withMediaUrl(array $row): array
    {
        $row['image_url'] = $this->mediaPublicUrl($row['image'] ?? null, $row['url'] ?? null);

        return $row;
    }
}
