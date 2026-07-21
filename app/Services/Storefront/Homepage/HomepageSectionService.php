<?php

namespace App\Services\Storefront\Homepage;

use Illuminate\Support\Str;

/**
 * Normalize, seed, and present homepage_sections for admin + public API.
 */
class HomepageSectionService
{
    private const UPLOAD_DIR = 'uploads/storefront_homepage';

    private const WP = 'https://gamesspoteg.com/wp-content/uploads';

    public function __construct(private SectionTypeRegistry $registry)
    {
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
    public function normalizeSections($sections): array
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
                'settings' => $this->normalizeSettings($type, is_array($row['settings'] ?? null) ? $row['settings'] : []),
            ];
        }

        return $normalized === [] ? $this->defaultSections() : array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function normalizeSettings(string $type, array $settings): array
    {
        $defaults = $this->registry->get($type)['default_settings'] ?? [];

        return match ($type) {
            'hero_slider' => [
                'slides' => $this->normalizeSlides($settings['slides'] ?? []),
            ],
            'promo_tiles' => [
                'tiles' => $this->normalizeTiles($settings['tiles'] ?? []),
            ],
            'video' => [
                'url' => mb_substr(trim((string) ($settings['url'] ?? '')), 0, 1000),
                'poster' => mb_substr(trim((string) ($settings['poster'] ?? '')), 0, 1000),
                'title' => $this->localeMap($settings['title'] ?? null, 120),
            ],
            'promo_banners' => [
                'max' => max(1, min(24, (int) ($settings['max'] ?? 12))),
            ],
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
            'brand_slider' => [
                'limit' => max(1, min(48, (int) ($settings['limit'] ?? 24))),
            ],
            'bestsellers' => [
                'per_page' => max(1, min(24, (int) ($settings['per_page'] ?? 6))),
                'in_stock_only' => filter_var($settings['in_stock_only'] ?? true, FILTER_VALIDATE_BOOLEAN),
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
    public function presentForApi(array $sections, string $locale): array
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

            $out[] = [
                'id' => $section['id'],
                'type' => $type,
                'settings' => $this->presentSettings($type, $section['settings'] ?? [], $locale),
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
            $out[] = [
                'id' => $section['id'],
                'type' => $type,
                'enabled' => (bool) ($section['enabled'] ?? true),
                'settings' => $settings,
            ];
        }

        return $out;
    }

    public function mediaPublicUrl(?string $image, ?string $url): ?string
    {
        $image = trim((string) $image);
        if ($image !== '') {
            return asset(self::UPLOAD_DIR.'/'.$image);
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
            'video' => [
                'url' => (string) ($settings['url'] ?? ''),
                'poster' => (string) ($settings['poster'] ?? ''),
                'title' => $this->pickLocale($settings['title'] ?? [], $locale),
            ],
            default => $settings,
        };
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
