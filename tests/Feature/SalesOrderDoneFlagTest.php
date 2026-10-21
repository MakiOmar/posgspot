<?php

namespace Tests\Feature;

use App\BusinessLocation;
use App\Contact;
use App\Product;
use App\Transaction;
use App\Unit;
use App\User;
use App\Utils\ProductUtil;
use App\Variation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Staff "Done" checkbox on the sales order create / edit form.
 */
class SalesOrderDoneFlagTest extends TestCase
{
    use DatabaseTransactions;

    private const BUSINESS_ID = 1;

    private function user(): User
    {
        $user = User::where('business_id', self::BUSINESS_ID)->where('allow_login', 1)->whereNotNull('username')->first();
        if (! $user) {
            $this->markTestSkipped('No login user for business 1.');
        }
        config(['constants.administrator_usernames' => $user->username]);

        return $user;
    }

    private function location(): int
    {
        $id = BusinessLocation::where('business_id', self::BUSINESS_ID)->value('id');
        if (! $id) {
            $this->markTestSkipped('No location for business 1.');
        }

        return (int) $id;
    }

    private function physicalProduct(): Product
    {
        $sku = 'ZZDONE-'.random_int(100000, 999999);
        $product = Product::create([
            'name' => 'ZZ Done '.$sku,
            'business_id' => self::BUSINESS_ID,
            'type' => 'single',
            'unit_id' => Unit::where('business_id', self::BUSINESS_ID)->value('id'),
            'tax_type' => 'exclusive',
            'enable_stock' => 0,
            'alert_quantity' => 0,
            'sku' => $sku,
            'barcode_type' => 'C128',
            'created_by' => 1,
            'is_inactive' => 0,
            'not_for_selling' => 0,
        ]);
        app(ProductUtil::class)->createSingleProductVariation($product, $sku, 100, 100, 0, 100, 100);

        return $product;
    }

    private function contact(): Contact
    {
        return Contact::create([
            'business_id' => self::BUSINESS_ID,
            'type' => 'customer',
            'name' => 'Done Buyer',
            'mobile' => '0100'.random_int(1000000, 9999999),
            'contact_id' => 'CO-D'.random_int(100000, 999999),
            'created_by' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(Product $product, Contact $contact, array $extra): array
    {
        return array_merge([
            'is_direct_sale' => 1,
            'location_id' => $this->location(),
            'contact_id' => $contact->id,
            'discount_type' => 'fixed',
            'discount_amount' => 0,
            'tax_rate_id' => null,
            'final_total' => 100,
            'products' => [[
                'product_id' => $product->id,
                'variation_id' => Variation::where('product_id', $product->id)->value('id'),
                'quantity' => 1,
                'unit_price' => 100,
                'unit_price_inc_tax' => 100,
                'item_tax' => 0,
                'tax_id' => null,
                'line_discount_type' => 'fixed',
                'line_discount_amount' => 0,
                'enable_stock' => 0,
                'product_type' => 'single',
            ]],
        ], $extra);
    }

    public function test_sales_order_done_flag_is_saved_on_create_and_cleared_on_edit(): void
    {
        $user = $this->user();
        $product = $this->physicalProduct();
        $contact = $this->contact();
        $session = ['user.business_id' => self::BUSINESS_ID, 'user.id' => $user->id];

        $this->actingAs($user)->withSession($session)
            ->from('/sells/create?sale_type=sales_order')
            ->post('/pos', $this->payload($product, $contact, ['type' => 'sales_order', 'status' => 'ordered', 'is_done' => 1]))
            ->assertSessionHas('status.success', 1);

        $so = Transaction::where('contact_id', $contact->id)->where('type', 'sales_order')->latest('id')->firstOrFail();
        $this->assertTrue((bool) $so->is_done);

        $field = view('sell.partials.sales_order_done_field', ['is_done' => true])->render();
        $this->assertStringContainsString('<input type="hidden" name="is_done" value="0">', $field);
        $this->assertMatchesRegularExpression('/<input[^>]*checked[^>]*id="is_done"|<input[^>]*id="is_done"[^>]*checked/', $field);

        $line = $so->sell_lines()->firstOrFail();
        $edit = $this->payload($product, $contact, ['status' => 'ordered', 'is_done' => 0]);
        $edit['products'][0]['transaction_sell_lines_id'] = $line->id;
        $this->actingAs($user)->withSession($session)
            ->from('/sells/'.$so->id.'/edit')
            ->put('/pos/'.$so->id, $edit)
            ->assertSessionHas('status.success', 1);

        $this->assertFalse((bool) $so->fresh()->is_done);
    }

    public function test_plain_sell_ignores_done_flag(): void
    {
        $user = $this->user();
        $contact = $this->contact();

        $this->actingAs($user)->withSession(['user.business_id' => self::BUSINESS_ID, 'user.id' => $user->id])
            ->from('/sells/create')
            ->post('/pos', $this->payload($this->physicalProduct(), $contact, ['status' => 'draft', 'is_done' => 1]))
            ->assertSessionHas('status.success', 1);

        $sell = Transaction::where('contact_id', $contact->id)->where('type', 'sell')->latest('id')->firstOrFail();
        $this->assertFalse((bool) $sell->is_done);
    }
}
