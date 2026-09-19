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
                            'label' => ['en' => 'PS5', 'ar' => ''],
                            'children' => [
                                [
                                    'id' => 'd',
                                    'type' => 'link',
                                    'category_id' => 30,
                                    'label' => ['en' => '', 'ar' => ''],
                                ],
                            ],
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
        $this->assertCount(2, $menu['physical'][1]['children']);
        $this->assertSame(20, $menu['physical'][1]['children'][0]['category_id']);
        $this->assertSame('group', $menu['physical'][1]['children'][1]['type']);
        $this->assertSame('PS5', $menu['physical'][1]['children'][1]['label']['en']);
        $this->assertSame(30, $menu['physical'][1]['children'][1]['children'][0]['category_id']);
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

    public function test_normalize_shop_menu_caps_depth(): void
    {
        $service = app(StorefrontSettingService::class);
        $deep = ['id' => 'l', 'type' => 'link', 'category_id' => 99, 'label' => ['en' => '', 'ar' => '']];
        for ($i = StorefrontSettingService::SHOP_MENU_MAX_DEPTH; $i >= 1; $i--) {
            $deep = [
                'id' => 'g'.$i,
                'type' => 'group',
                'label' => ['en' => 'L'.$i, 'ar' => ''],
                'children' => [$deep],
            ];
        }
        // One more group beyond the max — deepest group should become a link-only container or drop.
        $tooDeep = [
            'id' => 'overflow',
            'type' => 'group',
            'label' => ['en' => 'Too deep', 'ar' => ''],
            'children' => [$deep],
        ];

        $menu = $service->normalizeShopMenu(['physical' => [$tooDeep]]);
        $this->assertCount(1, $menu['physical']);
        $this->assertSame('group', $menu['physical'][0]['type']);

        $node = $menu['physical'][0];
        $depth = 1;
        while (($node['type'] ?? '') === 'group' && ! empty($node['children'][0])) {
            $node = $node['children'][0];
            $depth++;
        }
        $this->assertLessThanOrEqual(StorefrontSettingService::SHOP_MENU_MAX_DEPTH, $depth);
    }
}
