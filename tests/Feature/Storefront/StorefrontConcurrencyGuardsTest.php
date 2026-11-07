<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Contact;
use App\Coupon;
use App\Jobs\NotifyStaffOfStorefrontOrder;
use App\Product;
use App\Services\Storefront\CheckoutService;
use App\Services\Storefront\CouponService;
use App\Services\Storefront\Payment\StorefrontPaymentRecorder;
use App\Transaction;
use App\TransactionPayment;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Re-checks that run under row locks so two simultaneous requests cannot both pass
 * a check that was made earlier without a lock (coupon caps, last unit of stock, paid status).
 */
class StorefrontConcurrencyGuardsTest extends TestCase
{
    use DatabaseTransactions;

    protected int $businessId = 1;

    public function test_coupon_total_cap_is_rechecked_under_lock(): void
    {
        $contact = $this->createCustomer();
        $order = $this->createOrder($contact, 'paid');
        $coupon = $this->createCoupon(['max_uses_total' => 1, 'times_used' => 1]);

        $this->expectException(ValidationException::class);
        app(CouponService::class)->assertUsageLimitsUnderLock($coupon, $contact, (int) $order->id);
    }

    public function test_coupon_per_customer_cap_is_rechecked_under_lock(): void
    {
        $contact = $this->createCustomer();
        $coupons = app(CouponService::class);
        $coupon = $this->createCoupon(['max_uses_per_customer' => 1]);
        $coupons->recordRedemption($coupon, $this->createOrder($contact, 'paid'), $contact, 5);

        $this->expectException(ValidationException::class);
        $coupons->assertUsageLimitsUnderLock($coupon->fresh(), $contact, (int) $this->createOrder($contact, 'due')->id);
    }

    public function test_coupon_first_order_only_ignores_the_current_order(): void
    {
        $contact = $this->createCustomer();
        $order = $this->createOrder($contact, 'due');
        $coupon = $this->createCoupon(['first_order_only' => true]);

        app(CouponService::class)->assertUsageLimitsUnderLock($coupon, $contact, (int) $order->id);

        $this->createOrder($contact, 'paid');
        $this->expectException(ValidationException::class);
        app(CouponService::class)->assertUsageLimitsUnderLock($coupon, $contact, (int) $order->id);
    }

    public function test_stock_is_rechecked_under_lock_before_deduction(): void
    {
        [$variation, $locationId] = $this->stockedVariation(1);
        $line = [
            'product_id' => $variation->product_id,
            'variation_id' => $variation->id,
            'enable_stock' => 1,
            'quantity' => 1,
        ];

        $this->invokeStockCheck([$line], [[]], $locationId);

        $this->expectException(ValidationException::class);
        $this->invokeStockCheck([$line, $line], [[], []], $locationId);
    }

    public function test_stock_check_skips_digital_lines(): void
    {
        [$variation, $locationId] = $this->stockedVariation(0);
        $line = [
            'product_id' => $variation->product_id,
            'variation_id' => $variation->id,
            'enable_stock' => 1,
            'quantity' => 1,
        ];

        $this->invokeStockCheck([$line], [['digital' => ['kind' => 'game']]], $locationId);
        $this->addToAssertionCount(1);
    }

    public function test_mark_paid_twice_records_one_payment_and_one_alert(): void
    {
        Queue::fake();
        $order = $this->createOrder($this->createCustomer(), 'due');
        $recorder = app(StorefrontPaymentRecorder::class);

        $recorder->markPaid($order, $this->businessId, ['gateway' => 'test']);
        $recorder->markPaid(Transaction::find($order->id), $this->businessId, ['gateway' => 'test']);

        $this->assertSame('paid', Transaction::find($order->id)->payment_status);
        $this->assertSame(1, TransactionPayment::where('transaction_id', $order->id)->count());
        Queue::assertPushed(NotifyStaffOfStorefrontOrder::class, 1);
    }

    private function invokeStockCheck(array $products, array $checkoutItems, int $locationId): void
    {
        $method = new \ReflectionMethod(CheckoutService::class, 'lockAndAssertStock');
        $method->invoke(app(CheckoutService::class), $products, $checkoutItems, $locationId);
    }

    /** @return array{0: Variation, 1: int} */
    private function stockedVariation(float $qty): array
    {
        $locationId = (int) BusinessLocation::where('business_id', $this->businessId)->where('is_active', 1)->value('id');
        $product = Product::where('business_id', $this->businessId)->where('enable_stock', 1)->first();
        $variation = $product ? Variation::where('product_id', $product->id)->whereNull('deleted_at')->first() : null;
        if (! $locationId || ! $variation) {
            $this->markTestSkipped('No stocked product or active location in database.');
        }

        VariationLocationDetails::updateOrCreate(
            ['variation_id' => $variation->id, 'location_id' => $locationId],
            [
                'product_id' => $variation->product_id,
                'product_variation_id' => $variation->product_variation_id,
                'qty_available' => $qty,
            ]
        );

        return [$variation, $locationId];
    }

    private function createCustomer(): Contact
    {
        return Contact::create([
            'business_id' => $this->businessId,
            'type' => 'customer',
            'contact_status' => 'active',
            'name' => 'Race Tester',
            'first_name' => 'Race',
            'email' => 'race_'.uniqid().'@example.com',
            'mobile' => '+2010'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'created_by' => 1,
            'is_default' => 0,
        ]);
    }

    private function createOrder(Contact $contact, string $paymentStatus): Transaction
    {
        return Transaction::create([
            'business_id' => $this->businessId,
            'location_id' => BusinessLocation::where('business_id', $this->businessId)->where('is_active', 1)->value('id'),
            'type' => 'sell',
            'status' => 'final',
            'contact_id' => $contact->id,
            'invoice_no' => 'RACE-'.uniqid(),
            'storefront_order_id' => 'web-race-'.uniqid(),
            'transaction_date' => now(),
            'total_before_tax' => 100,
            'final_total' => 100,
            'created_by' => 1,
            'source' => 'storefront',
            'payment_status' => $paymentStatus,
        ]);
    }

    private function createCoupon(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'business_id' => $this->businessId,
            'code' => 'RACE'.strtoupper(substr(uniqid(), -6)),
            'name' => 'Race coupon',
            'type' => Coupon::TYPE_PERCENT_ORDER,
            'discount_amount' => 10,
            'min_order_subtotal' => 0,
            'is_active' => true,
            'channel' => Coupon::CHANNEL_STOREFRONT,
            'applies_to' => Coupon::APPLIES_ALL,
        ], $overrides));
    }
}
