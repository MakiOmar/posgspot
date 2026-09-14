<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Coupon;
use App\CouponRedemption;
use App\Transaction;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Account coupon wallet and payment_status order filter.
 */
class AccountCouponWalletTest extends TestCase
{
    protected int $businessId = 1;

    private function createCoupon(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'business_id' => $this->businessId,
            'code' => 'WALLET'.strtoupper(substr(uniqid(), -6)),
            'name' => 'Wallet coupon',
            'type' => Coupon::TYPE_PERCENT_ORDER,
            'discount_amount' => 10,
            'min_order_subtotal' => 0,
            'is_active' => true,
            'channel' => Coupon::CHANNEL_STOREFRONT,
            'applies_to' => Coupon::APPLIES_ALL,
            'stack_with_reward_points' => true,
        ], $overrides));
    }

    private function registerAndLogin(): array
    {
        Mail::fake();

        $email = 'wallet_'.uniqid().'@example.com';
        $register = $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Wallet',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        return [
            'token' => $register->json('data.token'),
            'contact_id' => (int) $register->json('data.contact.id'),
            'email' => $email,
        ];
    }

    public function test_save_coupon_to_wallet_and_list_unused(): void
    {
        $session = $this->registerAndLogin();
        $coupon = $this->createCoupon();

        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/account/coupons', ['code' => $coupon->code])
            ->assertCreated()
            ->assertJsonPath('data.code', $coupon->code);

        $this->withToken($session['token'])
            ->getJson('/api/storefront/v1/account/coupons')
            ->assertOk()
            ->assertJsonPath('data.0.code', $coupon->code);
    }

    public function test_cannot_save_unknown_or_expired_coupon(): void
    {
        $session = $this->registerAndLogin();

        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/account/coupons', ['code' => 'NOPE123'])
            ->assertStatus(422);

        $expired = $this->createCoupon(['ends_at' => now()->subDay()]);
        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/account/coupons', ['code' => $expired->code])
            ->assertStatus(422);
    }

    public function test_redeemed_wallet_code_moves_to_used_list(): void
    {
        $session = $this->registerAndLogin();
        $coupon = $this->createCoupon();

        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/account/coupons', ['code' => $coupon->code])
            ->assertCreated();

        $locationId = BusinessLocation::where('business_id', $this->businessId)
            ->where('is_active', 1)
            ->value('id');

        $transaction = Transaction::create([
            'business_id' => $this->businessId,
            'location_id' => $locationId,
            'type' => 'sell',
            'status' => 'final',
            'contact_id' => $session['contact_id'],
            'transaction_date' => now(),
            'total_before_tax' => 100,
            'final_total' => 90,
            'payment_status' => 'paid',
            'invoice_no' => 'WALLET-INV-'.uniqid(),
            'created_by' => 1,
            'source' => 'storefront',
        ]);

        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'business_id' => $this->businessId,
            'contact_id' => $session['contact_id'],
            'transaction_id' => $transaction->id,
            'discount_amount' => 10,
            'channel' => Coupon::CHANNEL_STOREFRONT,
            'redeemed_at' => now(),
        ]);

        $this->withToken($session['token'])
            ->getJson('/api/storefront/v1/account/coupons')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withToken($session['token'])
            ->getJson('/api/storefront/v1/account/coupons/used')
            ->assertOk()
            ->assertJsonPath('data.0.code', $coupon->code)
            ->assertJsonPath('data.0.order_id', $transaction->id)
            ->assertJsonPath('data.0.discount_amount', 10);
    }

    public function test_orders_can_filter_by_payment_status(): void
    {
        $session = $this->registerAndLogin();
        $locationId = BusinessLocation::where('business_id', $this->businessId)
            ->where('is_active', 1)
            ->value('id');

        if (empty($locationId)) {
            $this->markTestSkipped('No active business location in database.');
        }

        $paid = Transaction::create([
            'business_id' => $this->businessId,
            'location_id' => $locationId,
            'type' => 'sell',
            'status' => 'final',
            'contact_id' => $session['contact_id'],
            'transaction_date' => now(),
            'total_before_tax' => 50,
            'final_total' => 50,
            'payment_status' => 'paid',
            'invoice_no' => 'PAY-PAID-'.uniqid(),
            'created_by' => 1,
            'source' => 'storefront',
        ]);
        Transaction::create([
            'business_id' => $this->businessId,
            'location_id' => $locationId,
            'type' => 'sell',
            'status' => 'final',
            'contact_id' => $session['contact_id'],
            'transaction_date' => now(),
            'total_before_tax' => 50,
            'final_total' => 50,
            'payment_status' => 'due',
            'invoice_no' => 'PAY-DUE-'.uniqid(),
            'created_by' => 1,
            'source' => 'storefront',
        ]);

        $this->withToken($session['token'])
            ->getJson('/api/storefront/v1/account/orders?payment_status=bogus')
            ->assertStatus(422);

        $paidList = $this->withToken($session['token'])
            ->getJson('/api/storefront/v1/account/orders?payment_status=paid')
            ->assertOk()
            ->json('data');

        $ids = array_column($paidList, 'id');
        $this->assertContains($paid->id, $ids);
        $this->assertCount(1, array_filter($paidList, fn ($row) => (int) $row['id'] === $paid->id));
    }
}
