<?php

namespace Tests\Feature\Storefront;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Gift card categories forward Accounts `card_art` (generated artwork) with whitelisted keys only.
 */
class DigitalCardArtPassthroughTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.accounts.base' => 'https://accounts.test',
            'services.accounts.phone' => '01000000000',
            'services.accounts.password' => 'secret',
        ]);
        Cache::flush();
    }

    public function test_card_art_is_passed_through_with_absolute_urls(): void
    {
        Http::fake([
            'accounts.test/api/card-ctegories/list' => Http::response(['status' => true, 'data' => [
                [
                    'id' => 11,
                    'name' => 'PSN US 10$',
                    'price' => 600,
                    'poster_image' => null,
                    'card_art' => [
                        'brand' => 'playstation',
                        'brand_label' => 'PlayStation Store',
                        'value' => '$10',
                        'country_code' => 'US',
                        'flag_url' => 'images/card-art/flags/us.svg',
                        'logo_url' => 'https://accounts.test/images/card-art/brands/playstation.svg',
                        'color' => '#0070d1',
                        'supplier_cost' => '2.500',
                    ],
                ],
                [
                    'id' => 12,
                    'name' => 'Odd',
                    'price' => 100,
                    'poster_image' => null,
                    'card_art' => ['value' => '$5', 'brand' => '<b>', 'color' => 'red;background:url(x)'],
                ],
                ['id' => 13, 'name' => 'Poster', 'price' => 100, 'poster_image' => 'images/posters/p.webp', 'card_art' => null],
            ]]),
        ]);

        $response = $this->getJson('/api/storefront/v1/digital/card-categories')->assertOk();
        $rows = collect($response->json('data.categories'))->keyBy('id');

        $this->assertSame([
            'brand' => 'playstation',
            'brand_label' => 'PlayStation Store',
            'value' => '$10',
            'country_code' => 'us',
            'flag_url' => 'https://accounts.test/images/card-art/flags/us.svg',
            'logo_url' => 'https://accounts.test/images/card-art/brands/playstation.svg',
            'color' => '#0070d1',
        ], $rows[11]['card_art']);

        $this->assertSame('generic', $rows[12]['card_art']['brand']);
        $this->assertSame('#4b5563', $rows[12]['card_art']['color']);
        $this->assertNull($rows[12]['card_art']['flag_url']);
        $this->assertNull($rows[13]['card_art']);
    }

    public function test_classification_is_passed_through_and_sanitized(): void
    {
        Http::fake([
            'accounts.test/api/card-ctegories/list' => Http::response(['status' => true, 'data' => [
                [
                    'id' => 21,
                    'name' => 'Amazon KSA 100 SAR',
                    'price' => 600,
                    'poster_image' => 'images/posters/a.webp',
                    'card_art' => null,
                    'classification' => [
                        'brand' => 'amazon',
                        'brand_label' => 'Amazon',
                        'logo_url' => 'images/card-art/brands/amazon.svg',
                        'color' => '#ff9900',
                        'country' => 'SA',
                        'country_label' => 'Saudi Arabia',
                        'flag_url' => 'images/card-art/flags/sa.svg',
                        'supplier_cost' => '2.500',
                    ],
                ],
                [
                    'id' => 22,
                    'name' => 'Odd',
                    'price' => 100,
                    'classification' => ['brand' => '<b>', 'country' => 'zz-top', 'color' => 'red;x', 'flag_url' => 'images/x.svg'],
                ],
                ['id' => 23, 'name' => 'Old Accounts', 'price' => 100],
            ]]),
        ]);

        $rows = collect($this->getJson('/api/storefront/v1/digital/card-categories')->assertOk()->json('data.categories'))->keyBy('id');

        $this->assertSame([
            'brand' => 'amazon',
            'brand_label' => 'Amazon',
            'logo_url' => 'https://accounts.test/images/card-art/brands/amazon.svg',
            'color' => '#ff9900',
            'country' => 'sa',
            'country_label' => 'Saudi Arabia',
            'flag_url' => 'https://accounts.test/images/card-art/flags/sa.svg',
        ], $rows[21]['classification']);

        $this->assertSame([
            'brand' => 'other',
            'brand_label' => 'Other',
            'logo_url' => null,
            'color' => '#4b5563',
            'country' => 'other',
            'country_label' => 'Other',
            'flag_url' => null,
        ], $rows[22]['classification']);
        $this->assertNull($rows[23]['classification']);
    }
}
