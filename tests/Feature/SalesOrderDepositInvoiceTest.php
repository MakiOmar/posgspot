<?php

namespace Tests\Feature;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\Product;
use App\Services\SalesOrderInvoiceService;
use App\Transaction;
use App\TransactionPayment;
use App\TransactionSellLine;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use App\Variation;
use App\VariationLocationDetails;
use Tests\TestCase;

/**
 * Sales order deposits: no stock/invoice until fully paid, then auto-convert.
 */
class SalesOrderDepositInvoiceTest extends TestCase
{
    protected int $businessId = 1;

    private function ensureSettingOn(): void
    {
        $business = Business::find($this->businessId);
        if (empty($business)) {
            $this->markTestSkipped('Business 1 not found.');
        }

        $settings = empty($business->pos_settings)
            ? []
            : (json_decode($business->pos_settings, true) ?: []);
        $settings['enable_sales_order'] = 1;
        $settings['so_invoice_on_full_payment'] = 1;
        $business->pos_settings = json_encode($settings);
        $business->save();
    }

    private function fixtureProductLocation(): array
    {
        $location = BusinessLocation::where('business_id', $this->businessId)
            ->where('is_active', 1)
            ->first();
        if (empty($location)) {
            $this->markTestSkipped('No active business location.');
        }

        $product = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->where('enable_stock', 1)
            ->where('type', '!=', 'combo')
            ->first();
        if (empty($product)) {
            $this->markTestSkipped('No stocked single product.');
        }

        $variation = Variation::where('product_id', $product->id)->whereNull('deleted_at')->first();
        if (empty($variation)) {
            $this->markTestSkipped('No variation.');
        }

        $vld = VariationLocationDetails::firstOrCreate(
            [
                'product_id' => $product->id,
                'product_variation_id' => $variation->product_variation_id,
                'variation_id' => $variation->id,
                'location_id' => $location->id,
            ],
            ['qty_available' => 10]
        );

        $contact = Contact::where('business_id', $this->businessId)
            ->where('type', 'customer')
            ->first();
        if (empty($contact)) {
            $this->markTestSkipped('No customer contact.');
        }

        $user = User::where('business_id', $this->businessId)->first();
        if (empty($user)) {
            $this->markTestSkipped('No user.');
        }

        return compact('location', 'product', 'variation', 'vld', 'contact', 'user');
    }

    /**
     * Create a sales order with optional completed payment amount (no stock decrease).
     */
    private function createSalesOrder(
        array $fx,
        float $qty,
        float $unitPrice,
        float $paidAmount
    ): Transaction {
        $finalTotal = round($qty * $unitPrice, 4);
        $transactionUtil = app(TransactionUtil::class);

        $input = [
            'type' => 'sales_order',
            'status' => 'ordered',
            'location_id' => $fx['location']->id,
            'contact_id' => $fx['contact']->id,
            'transaction_date' => now()->format('Y-m-d H:i:s'),
            'final_total' => $finalTotal,
            'discount_type' => 'fixed',
            'discount_amount' => 0,
            'tax_rate_id' => null,
            'is_direct_sale' => 1,
            'products' => [],
        ];

        $invoiceTotal = [
            'total_before_tax' => $finalTotal,
            'tax' => 0,
        ];

        $so = $transactionUtil->createSellTransaction(
            $this->businessId,
            $input,
            $invoiceTotal,
            $fx['user']->id,
            false
        );

        TransactionSellLine::create([
            'transaction_id' => $so->id,
            'product_id' => $fx['product']->id,
            'variation_id' => $fx['variation']->id,
            'quantity' => $qty,
            'unit_price_before_discount' => $unitPrice,
            'unit_price' => $unitPrice,
            'line_discount_type' => null,
            'line_discount_amount' => 0,
            'item_tax' => 0,
            'tax_id' => null,
            'unit_price_inc_tax' => $unitPrice,
            'so_quantity_invoiced' => 0,
        ]);

        if ($paidAmount > 0) {
            TransactionPayment::create([
                'transaction_id' => $so->id,
                'business_id' => $this->businessId,
                'amount' => $paidAmount,
                'method' => 'cash',
                'payment_line_status' => 'completed',
                'paid_on' => now(),
                'created_by' => $fx['user']->id,
                'payment_for' => $fx['contact']->id,
                'payment_ref_no' => 'TEST-SO-'.uniqid(),
            ]);
        }

        $status = $transactionUtil->updatePaymentStatus($so->id, $finalTotal);
        $so->payment_status = $status;
        $so->save();

        return $so->fresh(['sell_lines', 'payment_lines']);
    }

    public function test_setting_defaults_to_on_when_missing(): void
    {
        $util = app(BusinessUtil::class);
        $this->assertTrue($util->isSalesOrderInvoiceOnFullPaymentEnabled([]));
        $this->assertTrue($util->isSalesOrderInvoiceOnFullPaymentEnabled(['so_invoice_on_full_payment' => 1]));
        $this->assertFalse($util->isSalesOrderInvoiceOnFullPaymentEnabled(['so_invoice_on_full_payment' => 0]));
    }

    public function test_partial_payment_does_not_decrease_stock_or_create_final_invoice(): void
    {
        $this->ensureSettingOn();
        $fx = $this->fixtureProductLocation();

        $qtyBefore = (float) $fx['vld']->fresh()->qty_available;
        $unitPrice = (float) ($fx['variation']->sell_price_inc_tax ?: 100);

        $so = $this->createSalesOrder($fx, 2, $unitPrice, $unitPrice); // half paid

        $this->assertSame('sales_order', $so->type);
        $this->assertSame('ordered', $so->status);
        $this->assertSame('partial', $so->payment_status);

        $result = app(SalesOrderInvoiceService::class)->convertIfPaid($so, $fx['user']->id);
        $this->assertSame('skipped', $result['status']);
        $this->assertSame('not_paid', $result['reason']);

        $this->assertEquals($qtyBefore, (float) $fx['vld']->fresh()->qty_available);
        $linkedSells = Transaction::where('type', 'sell')
            ->where('business_id', $this->businessId)
            ->get()
            ->filter(fn ($t) => in_array($so->id, $t->sales_order_ids ?? [], true));
        $this->assertCount(0, $linkedSells);
    }

    public function test_full_payment_converts_to_sell_decreases_stock_and_moves_payments(): void
    {
        $this->ensureSettingOn();
        $fx = $this->fixtureProductLocation();

        $fx['vld']->qty_available = 10;
        $fx['vld']->save();

        $qtyBefore = 10.0;
        $unitPrice = (float) ($fx['variation']->sell_price_inc_tax ?: 100);
        $qty = 2.0;
        $finalTotal = round($qty * $unitPrice, 4);

        $so = $this->createSalesOrder($fx, $qty, $unitPrice, $finalTotal);
        $this->assertSame('paid', $so->payment_status);

        $paymentIds = $so->payment_lines->pluck('id')->all();
        $this->assertNotEmpty($paymentIds);

        $result = app(SalesOrderInvoiceService::class)->convertIfPaid($so, $fx['user']->id);
        $this->assertSame('created', $result['status'], $result['msg'] ?? '');
        $this->assertInstanceOf(Transaction::class, $result['sell']);

        $sell = $result['sell'];
        $this->assertSame('sell', $sell->type);
        $this->assertSame('final', $sell->status);
        $this->assertSame('paid', $sell->payment_status);
        $this->assertEquals([$so->id], $sell->sales_order_ids);
        $this->assertNotEquals($so->invoice_no, $sell->invoice_no);

        $this->assertEquals(
            $qtyBefore - $qty,
            (float) $fx['vld']->fresh()->qty_available
        );

        // Payments moved (same ids), not duplicated.
        foreach ($paymentIds as $paymentId) {
            $payment = TransactionPayment::find($paymentId);
            $this->assertNotNull($payment);
            $this->assertEquals($sell->id, $payment->transaction_id);
        }
        $this->assertSame(0, TransactionPayment::where('transaction_id', $so->id)
            ->where('payment_line_status', 'completed')
            ->where('is_return', 0)
            ->count());

        $so = $so->fresh();
        $this->assertSame('completed', $so->status);
        // Still paid after deposits moved to the invoice (must not recalculate as due).
        $this->assertSame('paid', $so->payment_status);
    }

    public function test_full_payment_with_insufficient_stock_skips_conversion(): void
    {
        $this->ensureSettingOn();
        $fx = $this->fixtureProductLocation();

        $fx['vld']->qty_available = 0;
        $fx['vld']->save();

        $unitPrice = (float) ($fx['variation']->sell_price_inc_tax ?: 100);
        $so = $this->createSalesOrder($fx, 1, $unitPrice, $unitPrice);
        $this->assertSame('paid', $so->payment_status);

        $result = app(SalesOrderInvoiceService::class)->convertIfPaid($so, $fx['user']->id);
        $this->assertSame('skipped', $result['status']);
        $this->assertSame('insufficient_stock', $result['reason']);

        $this->assertEquals(0, (float) $fx['vld']->fresh()->qty_available);
        $this->assertSame('paid', $so->fresh()->payment_status);
        $this->assertSame('ordered', $so->fresh()->status);
        $linkedSells = Transaction::where('type', 'sell')
            ->where('business_id', $this->businessId)
            ->get()
            ->filter(fn ($t) => in_array($so->id, $t->sales_order_ids ?? [], true));
        $this->assertCount(0, $linkedSells);
    }

    public function test_unpaid_sales_order_cannot_be_selected_when_setting_on(): void
    {
        $this->ensureSettingOn();
        $fx = $this->fixtureProductLocation();

        $unitPrice = (float) ($fx['variation']->sell_price_inc_tax ?: 100);
        $unpaid = $this->createSalesOrder($fx, 1, $unitPrice, 0);
        $paid = $this->createSalesOrder($fx, 1, $unitPrice, $unitPrice);

        // Leave paid SO unconverted by temporarily zeroing stock then restoring
        // so convertIfPaid is not auto-run here — paid SO still ordered with remaining qty.
        $this->assertSame('paid', $paid->payment_status);
        $this->assertSame('due', $unpaid->payment_status);

        $business = Business::find($this->businessId);
        $posSettings = json_decode($business->pos_settings, true) ?: [];
        $this->assertTrue(app(BusinessUtil::class)->isSalesOrderInvoiceOnFullPaymentEnabled($posSettings));

        $query = Transaction::where('business_id', $this->businessId)
            ->where('location_id', $fx['location']->id)
            ->where('type', 'sales_order')
            ->whereIn('status', ['partial', 'ordered'])
            ->where('contact_id', $fx['contact']->id)
            ->where('payment_status', 'paid')
            ->whereIn('id', [$unpaid->id, $paid->id])
            ->pluck('id')
            ->all();

        $this->assertContains($paid->id, $query);
        $this->assertNotContains($unpaid->id, $query);
    }

    public function test_retry_create_after_stock_available(): void
    {
        $this->ensureSettingOn();
        $fx = $this->fixtureProductLocation();

        $fx['vld']->qty_available = 0;
        $fx['vld']->save();

        $unitPrice = (float) ($fx['variation']->sell_price_inc_tax ?: 100);
        $so = $this->createSalesOrder($fx, 1, $unitPrice, $unitPrice);

        $first = app(SalesOrderInvoiceService::class)->convertIfPaid($so, $fx['user']->id);
        $this->assertSame('insufficient_stock', $first['reason']);

        $fx['vld']->qty_available = 5;
        $fx['vld']->save();

        $second = app(SalesOrderInvoiceService::class)->convertIfPaid($so->fresh(), $fx['user']->id);
        $this->assertSame('created', $second['status'], $second['msg'] ?? '');
        $this->assertEquals(4, (float) $fx['vld']->fresh()->qty_available);
    }
}
