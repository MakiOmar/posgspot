<?php

namespace Tests\Feature;

use App\Services\SellActivityChangesService;
use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Sell / sales order activity log shows what changed: product lines and header fields.
 */
class SellActivityChangesTest extends TestCase
{
    use DatabaseTransactions;

    private function service(): SellActivityChangesService
    {
        return app(SellActivityChangesService::class);
    }

    public function test_line_changes_detect_added_removed_and_updated_lines(): void
    {
        $before = [
            1 => ['product' => 'FIFA 26', 'qty' => 1.0, 'price' => 100.0],
            2 => ['product' => 'Controller', 'qty' => 2.0, 'price' => 50.0],
            3 => ['product' => 'Headset', 'qty' => 1.0, 'price' => 70.0],
        ];
        $after = [
            1 => ['product' => 'FIFA 26', 'qty' => 1.0, 'price' => 100.0],
            2 => ['product' => 'Controller', 'qty' => 3.0, 'price' => 45.0],
            4 => ['product' => 'PS Plus', 'qty' => 1.0, 'price' => 30.0],
        ];

        $changes = collect($this->service()->lineChanges($before, $after))->keyBy('product');

        $this->assertCount(3, $changes);
        $this->assertSame('updated', $changes['Controller']['change']);
        $this->assertSame([2.0, 3.0, 50.0, 45.0], [$changes['Controller']['qty_from'], $changes['Controller']['qty_to'], $changes['Controller']['price_from'], $changes['Controller']['price_to']]);
        $this->assertSame('added', $changes['PS Plus']['change']);
        $this->assertSame('removed', $changes['Headset']['change']);
        $this->assertFalse($changes->has('FIFA 26'));
    }

    public function test_field_changes_report_only_changed_header_fields(): void
    {
        $before = new Transaction(['additional_notes' => 'old note', 'staff_note' => null, 'shipping_charges' => 10, 'discount_type' => 'fixed', 'discount_amount' => 0]);
        $after = new Transaction(['additional_notes' => 'new note', 'staff_note' => null, 'shipping_charges' => 10, 'discount_type' => 'percentage', 'discount_amount' => 5]);

        $changes = collect($this->service()->fieldChanges($before, $after))->keyBy('label');

        $this->assertSame(['old note', 'new note'], [$changes['sale.sell_note']['from'], $changes['sale.sell_note']['to']]);
        $this->assertSame([null, '5%'], [$changes['sale.discount']['from'], $changes['sale.discount']['to']]);
        $this->assertFalse($changes->has('sale.shipping_charges'));
        $this->assertFalse($changes->has('sale.staff_note'));
    }

    public function test_added_properties_list_every_saved_line_as_added(): void
    {
        $line = \App\TransactionSellLine::whereNull('parent_sell_line_id')->whereHas('product')->latest('id')->first();
        if (! $line) {
            $this->markTestSkipped('No sell lines in the database.');
        }

        $properties = $this->service()->addedProperties((int) $line->transaction_id);
        $products = collect($properties['line_changes'])->pluck('product')->all();

        $this->assertNotEmpty($products);
        $this->assertContains('added', collect($properties['line_changes'])->pluck('change')->unique()->all());
        $this->assertTrue(collect($products)->contains(fn ($name) => str_starts_with($name, $line->product->name)));
    }

    public function test_sales_orders_log_status_and_total_like_sells(): void
    {
        $order = new Transaction(['type' => 'sales_order']);

        $this->assertContains('status', $order->log_properties);
        $this->assertContains('final_total', $order->log_properties);
    }

    public function test_sell_details_activity_shows_line_and_field_changes(): void
    {
        $user = User::where('business_id', 1)->where('allow_login', 1)->whereNotNull('username')->first();
        $sell = Transaction::where('business_id', 1)->where('type', 'sell')->latest('id')->first();
        if (! $user || ! $sell) {
            $this->markTestSkipped('Needs a login user and a sell for business 1.');
        }
        config(['constants.administrator_usernames' => $user->username]);
        $this->actingAs($user);

        activity()->performedOn($sell)->causedBy($user)->withProperties([
            'line_changes' => [['change' => 'added', 'product' => 'Activity Test Game', 'qty_from' => null, 'qty_to' => 2, 'price_from' => null, 'price_to' => 150]],
            'field_changes' => [['label' => 'sale.sell_note', 'type' => 'text', 'from' => null, 'to' => 'Gift wrap please']],
        ])->log('edited');

        $this->get('/sells/' . $sell->id)
            ->assertOk()
            ->assertSee('Activity Test Game')
            ->assertSee('Gift wrap please')
            ->assertSee(__('lang_v1.activity_line_added'));
    }
}
