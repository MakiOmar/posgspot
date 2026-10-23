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
}
