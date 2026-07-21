<?php

namespace App\Services\Storefront\Homepage;

/**
 * Registry of homepage section types (admin builder + public API).
 */
class SectionTypeRegistry
{
    /**
     * @return array<string, array{label: string, max_instances: int|null, default_settings: array<string, mixed>}>
     */
    public function all(): array
    {
        return [
            'hero_slider' => [
                'label' => 'Hero slider',
                'max_instances' => 1,
                'default_settings' => [
                    'slides' => [],
                ],
            ],
            'promo_tiles' => [
                'label' => 'Promo tiles',
                'max_instances' => 1,
                'default_settings' => [
                    'tiles' => [],
                ],
            ],
            'video' => [
                'label' => 'Video',
                'max_instances' => 1,
                'default_settings' => [
                    'url' => '',
                    'poster' => '',
                    'title' => ['en' => '', 'ar' => ''],
                ],
            ],
            // Legacy: pulls from Storefront Settings → Banners (placement=home).
            'promo_banners' => [
                'label' => 'Promo banners (legacy Banners tab)',
                'max_instances' => 1,
                'default_settings' => [
                    'max' => 12,
                ],
            ],
            // Preferred: one banner per section with fields in the builder.
            'promo_banner' => [
                'label' => 'Promo banner',
                'max_instances' => null,
                'default_settings' => [
                    'image' => null,
                    'url' => '',
                    'link' => '',
                    'title' => ['en' => '', 'ar' => ''],
                ],
            ],
            'featured_products' => [
                'label' => 'Featured products',
                'max_instances' => 1,
                'default_settings' => [
                    'per_page' => 8,
                ],
            ],
            'top_categories' => [
                'label' => 'Top categories',
                'max_instances' => 1,
                'default_settings' => [
                    'limit' => 8,
                ],
            ],
            'category_shelves' => [
                'label' => 'Category shelves (legacy flags)',
                'max_instances' => 1,
                'default_settings' => [
                    'limit' => 6,
                    'products_per_shelf' => 6,
                ],
            ],
            // Preferred: pick a category per section (replaces shelves long-term).
            'category_shelf' => [
                'label' => 'Category shelf',
                'max_instances' => null,
                'default_settings' => [
                    'category_id' => null,
                    'products_per_shelf' => 6,
                ],
            ],
            'brand_slider' => [
                'label' => 'Shop by brand',
                'max_instances' => 1,
                'default_settings' => [
                    'limit' => 24,
                ],
            ],
            'bestsellers' => [
                'label' => 'Bestsellers',
                'max_instances' => 1,
                'default_settings' => [
                    'per_page' => 6,
                    'in_stock_only' => true,
                ],
            ],
            'recently_viewed' => [
                'label' => 'Recently viewed',
                'max_instances' => 1,
                'default_settings' => [
                    'limit' => 8,
                ],
            ],
        ];
    }

    public function has(string $type): bool
    {
        return array_key_exists($type, $this->all());
    }

    /**
     * @return array{label: string, max_instances: int|null, default_settings: array<string, mixed>}|null
     */
    public function get(string $type): ?array
    {
        return $this->all()[$type] ?? null;
    }

    /**
     * Compact list for the admin Vue builder dropdown.
     *
     * @return array<int, array{type: string, label: string, max_instances: int|null}>
     */
    public function forAdmin(): array
    {
        $out = [];
        foreach ($this->all() as $type => $meta) {
            $out[] = [
                'type' => $type,
                'label' => $meta['label'],
                'max_instances' => $meta['max_instances'],
            ];
        }

        return $out;
    }
}
