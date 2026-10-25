<?php

namespace Tests\Feature;

use App\Product;
use App\User;
use App\Variation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Combo edit form must keep the saved selling price: rebuilding it from the 2-decimal margin
 * turned 12,000 into 12,000.04 on every save.
 */
class ComboProductPriceTest extends TestCase
{
    use DatabaseTransactions;

    private const BUSINESS_ID = 1;

    public function test_combo_edit_form_keeps_saved_selling_price(): void
    {
        $admin = User::where('business_id', self::BUSINESS_ID)
            ->get()
            ->first(fn (User $user) => $user->hasRole('Admin#'.self::BUSINESS_ID));
        $product = Product::where('business_id', self::BUSINESS_ID)->where('type', 'combo')->first();
        if (! $admin || ! $product) {
            $this->markTestSkipped('Needs an admin and a combo product for business 1.');
        }

        Variation::where('product_id', $product->id)->update([
            'default_sell_price' => 12000,
            'sell_price_inc_tax' => 12000,
            'profit_percent' => 132.92,
        ]);

        $this->actingAs($admin)
            ->post('/products/product_form_part', ['type' => 'combo', 'action' => 'edit', 'product_id' => $product->id])
            ->assertOk()
            ->assertSee('name="selling_price" type="text" value="12,000.00"', false)
            ->assertSee('update_net_total_amount(true)', false);
    }
}
