<?php

namespace Tests\Feature;

use App\BusinessLocation;
use App\Product;
use App\PurchaseLine;
use App\Services\ProductDeletionGuard;
use App\Transaction;
use App\TransactionSellLine;
use App\User;
use App\Variation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Sold products must never be hard-deleted: sell lines cascade with the product
 * and would vanish from past invoices. Staff deactivate them instead.
 */
class ProductDeletionGuardTest extends TestCase
{
    use DatabaseTransactions;

    private const BUSINESS_ID = 1;

    private function admin(): User
    {
        $user = User::where('business_id', self::BUSINESS_ID)->where('allow_login', 1)->whereNotNull('username')->first();
        if (! $user) {
            $this->markTestSkipped('No login user for business 1.');
        }
        config(['constants.administrator_usernames' => $user->username]);

        return $user;
    }

    /** A product with no purchase lines, so only the sales check can block its deletion. */
    private function productWithoutPurchases(): Product
    {
        $product = Product::where('business_id', self::BUSINESS_ID)
            ->whereNotIn('id', PurchaseLine::query()->select('product_id'))
            ->whereHas('variations')
            ->first();
        if (! $product) {
            $this->markTestSkipped('No product without purchases for business 1.');
        }

        return $product;
    }

    private function sellOnce(Product $product): TransactionSellLine
    {
        $locationId = BusinessLocation::where('business_id', self::BUSINESS_ID)->value('id');
        $variation = Variation::where('product_id', $product->id)->first();

        $sell = Transaction::create([
            'business_id' => self::BUSINESS_ID,
            'location_id' => $locationId,
            'type' => 'sell',
            'status' => 'final',
            'transaction_date' => now(),
            'total_before_tax' => 100,
            'final_total' => 100,
            'created_by' => 1,
        ]);

        return TransactionSellLine::create([
            'transaction_id' => $sell->id,
            'product_id' => $product->id,
            'variation_id' => $variation->id,
            'quantity' => 1,
            'unit_price' => 100,
            'unit_price_inc_tax' => 100,
            'item_tax' => 0,
            'line_discount_amount' => 0,
        ]);
    }

    public function test_guard_flags_only_sold_products(): void
    {
        $product = $this->productWithoutPurchases();
        $guard = app(ProductDeletionGuard::class);

        $alreadySold = $guard->hasSales(self::BUSINESS_ID, $product->id);
        $this->sellOnce($product);

        $this->assertTrue($guard->hasSales(self::BUSINESS_ID, $product->id));
        $this->assertSame([], $guard->productIdsWithSales(self::BUSINESS_ID, []));
        $this->assertFalse($guard->hasSales(999999, $product->id), 'Other businesses must not see these sales.');
        if (! $alreadySold) {
            $this->assertSame([$product->id], $guard->productIdsWithSales(self::BUSINESS_ID, [$product->id]));
        }
    }

    public function test_single_delete_refuses_sold_product_and_keeps_sell_line(): void
    {
        $product = $this->productWithoutPurchases();
        $line = $this->sellOnce($product);

        $this->actingAs($this->admin())->withSession(['user.business_id' => self::BUSINESS_ID])
            ->deleteJson('/products/'.$product->id, [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('msg', __('lang_v1.product_has_sales_deactivate'));

        $this->assertNotNull(Product::find($product->id));
        $this->assertNotNull(TransactionSellLine::find($line->id));
    }

    public function test_mass_delete_skips_sold_product_and_keeps_sell_line(): void
    {
        $product = $this->productWithoutPurchases();
        $line = $this->sellOnce($product);

        $this->actingAs($this->admin())->withSession(['user.business_id' => self::BUSINESS_ID])
            ->post('/products/mass-delete', ['selected_rows' => (string) $product->id])
            ->assertRedirect()
            ->assertSessionHas('status.msg', __('lang_v1.products_with_sales_not_deleted'));

        $this->assertNotNull(Product::find($product->id));
        $this->assertNotNull(TransactionSellLine::find($line->id));
    }
}
