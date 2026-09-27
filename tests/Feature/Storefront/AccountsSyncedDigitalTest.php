<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Product;
use App\Services\AccountsCatalogService;
use App\Services\Storefront\CartValidationService;
use App\Services\Storefront\CatalogService;
use App\Services\Storefront\DigitalCatalogService;
use App\Services\Storefront\StorefrontSettingService;
use App\Variation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Per-offer POS products synced from Accounts are hidden from POS/shop but sellable as their digital line.
 */
class AccountsSyncedDigitalTest extends TestCase
{
    use DatabaseTransactions;

    private int $businessId = 1;

    private int $locationId;

    protected function setUp(): void
    {
        parent::setUp();
        $location = BusinessLocation::where('business_id', $this->businessId)->where('is_active', 1)->first();
        if (! $location) {
            $this->markTestSkipped('No active business location in database.');
        }
        $this->locationId = (int) $location->id;
        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$this->locationId],
            'default_fulfillment_location_id' => $this->locationId,
        ]);
        Cache::flush();
    }

    /**
     * @return array{sku:string,product_id:int,variation_id:int,active:bool}
     */
    private function syncOffer(string $sku, bool $active = true): array
    {
        $kind = str_starts_with($sku, 'ACCOUNTS-CARD-') ? 'card' : 'game';

        return app(AccountsCatalogService::class)->upsert($this->businessId, $kind, [
            ['sku' => $sku, 'name' => 'Synced '.$sku, 'price' => 500, 'active' => $active],
        ])[0];
    }

    private function validateLine(int $variationId, ?array $digital): array
    {
        $this->partialMock(DigitalCatalogService::class, function ($mock) {
            $mock->shouldReceive('resolveOfferPrice')->andReturn(500.0);
        });

        $item = ['variation_id' => $variationId, 'quantity' => 1];
        if ($digital !== null) {
            $item['digital'] = $digital;
        }

        return app(CartValidationService::class)->validate($this->businessId, [$item], $this->locationId);
    }

    private function gameDigital(int $gameId, string $platform = '5', string $type = 'primary'): array
    {
        return [
            'kind' => 'game', 'game_id' => $gameId, 'type' => $type, 'platform' => $platform,
            'line_key' => "ps{$platform}_{$type}_stock|game:{$gameId}", 'title' => 'Synced game',
        ];
    }

    private function assertRejected(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected the cart line to be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.variation_id', $e->errors());
        }
    }

    public function test_synced_hidden_product_is_sellable_only_as_its_own_digital_line(): void
    {
        $offer = $this->syncOffer('ACCOUNTS-GAME-980001-PS5-PRIMARY');

        $result = $this->validateLine($offer['variation_id'], $this->gameDigital(980001));
        $this->assertNotEmpty($result);

        $this->assertRejected(fn () => $this->validateLine($offer['variation_id'], $this->gameDigital(980002)));
        $this->assertRejected(fn () => $this->validateLine($offer['variation_id'], $this->gameDigital(980001, '4')));
        $this->assertRejected(fn () => $this->validateLine($offer['variation_id'], null));
    }

    public function test_inactive_synced_product_and_hidden_physical_product_stay_blocked(): void
    {
        $inactive = $this->syncOffer('ACCOUNTS-GAME-980003-PS5-SECONDARY', active: false);
        $this->assertRejected(fn () => $this->validateLine(
            $inactive['variation_id'],
            $this->gameDigital(980003, '5', 'secondary')
        ));

        $product = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->where('sku', 'not like', 'ACCOUNTS-%')
            ->first();
        $variation = $product ? Variation::where('product_id', $product->id)->first() : null;
        if (! $variation) {
            $this->markTestSkipped('No sellable product in database.');
        }
        $product->update(['not_for_selling' => 1]);

        // Digital meta must not unlock an ordinary hidden product.
        $this->assertRejected(fn () => $this->validateLine($variation->id, $this->gameDigital(980004)));
    }

    public function test_pos_offers_come_from_active_synced_products_and_fall_back_when_missing(): void
    {
        $primary = $this->syncOffer('ACCOUNTS-GAME-980005-PS5-PRIMARY');
        $this->syncOffer('ACCOUNTS-GAME-980005-PS5-SECONDARY', active: false);
        $card = $this->syncOffer('ACCOUNTS-CARD-980006');

        $catalog = app(DigitalCatalogService::class);
        $offers = $catalog->gamePosOffers($this->businessId, [980005, 980007]);

        $this->assertSame($primary['variation_id'], $offers[980005]['5']['primary']['variation_id']);
        $this->assertArrayNotHasKey('secondary', $offers[980005]['5']);
        // Unsynced game: clients use the shared placeholder SKUs.
        $this->assertArrayNotHasKey(980007, $offers);
        $this->assertSame($card['product_id'], $catalog->cardPosSkus($this->businessId, [980006])[980006]['product_id']);
    }

    public function test_accounts_categories_are_hidden_from_storefront_category_tree(): void
    {
        $this->syncOffer('ACCOUNTS-GAME-980008-PS4-PRIMARY');
        $this->syncOffer('ACCOUNTS-CARD-980009');
        Cache::flush();

        $this->assertContains(
            AccountsCatalogService::GAME_CATEGORY_SLUG,
            array_column(\App\Category::catAndSubCategories($this->businessId), 'slug')
        );
        $slugs = array_column(app(CatalogService::class)->getCategories($this->businessId), 'slug');

        $this->assertNotContains(AccountsCatalogService::GAME_CATEGORY_SLUG, $slugs);
        $this->assertNotContains(AccountsCatalogService::CARD_CATEGORY_SLUG, $slugs);
    }

    public function test_buy_again_rebuilds_digital_meta_from_synced_sku(): void
    {
        $game = AccountsCatalogService::digitalFromSku('ACCOUNTS-GAME-12-PS5-SECONDARY', 'Avatar (Secondary · PS5)', 800.0);
        $this->assertSame([
            'kind' => 'game', 'game_id' => 12, 'type' => 'secondary', 'platform' => '5',
            'line_key' => 'ps5_secondary_stock|game:12', 'title' => 'Avatar (Secondary · PS5)', 'price' => 800.0,
        ], $game);
        $this->assertSame('ACCOUNTS-GAME-12-PS5-SECONDARY', AccountsCatalogService::skuForDigital($game));

        $card = AccountsCatalogService::digitalFromSku('ACCOUNTS-CARD-7', 'PSN 50', 2500.0);
        $this->assertSame('card|category:7', $card['line_key']);
        $this->assertSame('ACCOUNTS-CARD-7', AccountsCatalogService::skuForDigital($card));

        $this->assertNull(AccountsCatalogService::digitalFromSku('ACCOUNTS-GAME-12-PS4-OFFLINE', null, 100.0));
        $this->assertNull(AccountsCatalogService::digitalFromSku('0139', null, 100.0));
    }
}
