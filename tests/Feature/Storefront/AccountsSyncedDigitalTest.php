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

    public function test_digital_meta_cannot_reprice_an_ordinary_product(): void
    {
        $variation = $this->ordinarySellableVariation();
        $this->setPlaceholders(['primary_product_id' => null, 'secondary_product_id' => null, 'gift_card_product_id' => null]);

        $this->assertRejected(fn () => $this->validateLine($variation->id, $this->gameDigital(980010)));
        $this->assertRejected(fn () => $this->validateLine($variation->id, [
            'kind' => 'card', 'card_category_id' => 980011, 'line_key' => 'card|category:980011',
        ]));
    }

    public function test_configured_placeholder_accepts_only_its_own_digital_kind(): void
    {
        $variation = $this->ordinarySellableVariation();
        $this->setPlaceholders([
            'primary_product_id' => null,
            'secondary_product_id' => null,
            'gift_card_product_id' => (int) $variation->product_id,
        ]);

        $card = ['kind' => 'card', 'card_category_id' => 980012, 'line_key' => 'card|category:980012'];
        $this->assertNotEmpty($this->validateLine($variation->id, $card));
        $this->assertRejected(fn () => $this->validateLine($variation->id, $this->gameDigital(980012)));
    }

    public function test_fractional_quantity_is_rejected(): void
    {
        $offer = $this->syncOffer('ACCOUNTS-GAME-980013-PS5-PRIMARY');
        $this->partialMock(DigitalCatalogService::class, function ($mock) {
            $mock->shouldReceive('resolveOfferPrice')->andReturn(500.0);
        });

        try {
            app(CartValidationService::class)->validate($this->businessId, [[
                'variation_id' => $offer['variation_id'],
                'quantity' => 0.01,
                'digital' => $this->gameDigital(980013),
            ]], $this->locationId);
            $this->fail('Expected a fractional quantity to be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.quantity', $e->errors());
        }
    }

    public function test_checkout_api_rejects_fractional_quantity_and_oversized_carts(): void
    {
        $line = ['variation_id' => 1, 'quantity' => 0.5];

        $this->postJson('/api/storefront/v1/cart/validate', ['items' => [$line]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);

        $tooMany = array_fill(0, CartValidationService::MAX_LINES + 1, ['variation_id' => 1, 'quantity' => 1]);
        $this->postJson('/api/storefront/v1/cart/validate', ['items' => $tooMany])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    /**
     * @param  array<string, int|null>  $ids
     */
    private function setPlaceholders(array $ids): void
    {
        $settings = app(StorefrontSettingService::class);
        $digital = $settings->get($this->businessId)['digital'] ?? [];
        $settings->save($this->businessId, [
            'selling_location_ids' => [$this->locationId],
            'default_fulfillment_location_id' => $this->locationId,
            'digital' => array_merge($digital, $ids),
        ]);
        Cache::flush();
        $this->assertSame($ids['gift_card_product_id'], $settings->get($this->businessId)['digital']['gift_card_product_id'] ?? null);
    }

    private function ordinarySellableVariation(): Variation
    {
        $product = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->where('sku', 'not like', 'ACCOUNTS-%')
            ->first();
        $variation = $product ? Variation::where('product_id', $product->id)->first() : null;
        if (! $variation) {
            $this->markTestSkipped('No sellable product in database.');
        }

        return $variation;
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

    public function test_synced_products_stay_out_of_their_category_product_grid(): void
    {
        $offer = $this->syncOffer('ACCOUNTS-GAME-980008-PS4-PRIMARY');
        $categoryId = (int) Product::find($offer['product_id'])->category_id;
        Cache::flush();

        $ids = collect(app(CatalogService::class)->listProducts($this->businessId, ['category_id' => $categoryId], 100)->items())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertNotContains($offer['product_id'], $ids);
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
