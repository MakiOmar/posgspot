<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\StorefrontSettingService;
use Tests\TestCase;

class StorefrontShopMenuSettingsTest extends TestCase
{
    public function test_normalize_shop_menu_empty_by_default(): void
    {
        $service = app(StorefrontSettingService::class);
        $menu = $service->normalizeShopMenu(null);

        $this->assertSame(['physical' => []], $menu);
    }

    public function test_normalize_shop_menu_keeps_ordered_links_and_groups(): void
    {
        $service = app(StorefrontSettingService::class);
        $menu = $service->normalizeShopMenu([
            'physical' => [
                [
                    'id' => 'a',
                    'type' => 'link',
                    'category_id' => 10,
                    'label' => ['en' => 'Consoles', 'ar' => ''],
                ],
                [
                    'id' => 'b',
                    'type' => 'group',
                    'label' => ['en' => 'Joysticks', 'ar' => 'جوستيك'],
                    'children' => [
                        [
                            'id' => 'c',
                            'type' => 'link',
                            'category_id' => 20,
                            'label' => ['en' => '', 'ar' => ''],
                        ],
                        [
                            'id' => 'nested-group',
                            'type' => 'group',
                            'label' => ['en' => 'Nope', 'ar' => ''],
                            'children' => [],
                        ],
                    ],
                ],
                [
                    'type' => 'link',
                    'category_id' => 0,
                ],
            ],
        ]);

        $this->assertCount(2, $menu['physical']);
        $this->assertSame('link', $menu['physical'][0]['type']);
        $this->assertSame(10, $menu['physical'][0]['category_id']);
        $this->assertSame('Consoles', $menu['physical'][0]['label']['en']);

        $this->assertSame('group', $menu['physical'][1]['type']);
        $this->assertSame('Joysticks', $menu['physical'][1]['label']['en']);
        $this->assertCount(1, $menu['physical'][1]['children']);
        $this->assertSame(20, $menu['physical'][1]['children'][0]['category_id']);
    }

    public function test_normalize_shop_menu_drops_group_without_label(): void
    {
        $service = app(StorefrontSettingService::class);
        $menu = $service->normalizeShopMenu([
            'physical' => [
                [
                    'id' => 'g',
                    'type' => 'group',
                    'label' => ['en' => '', 'ar' => ''],
                    'children' => [
                        ['id' => 'l', 'type' => 'link', 'category_id' => 5, 'label' => ['en' => '', 'ar' => '']],
                    ],
                ],
            ],
        ]);

        $this->assertSame([], $menu['physical']);
    }
}
