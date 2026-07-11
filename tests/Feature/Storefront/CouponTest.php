<?php

namespace Tests\Feature\Storefront;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\Coupon;
use App\CouponRedemption;
use App\Product;
use App\Services\Storefront\StorefrontSettingService;
use App\StorefrontSetting;
use App\Transaction;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Storefront promo code validation, cart totals, and checkout redemption.
 */
class CouponTest extends TestCase
{
    protected int $businessId = 1;

    private function setupCheckoutFixtures(): array
    {
        $location = BusinessLocation::where('business_id', $this->businessId)
            ->where('is_active', 1)
            ->first();

        if (empty($location)) {
            $this->markTestSkipped('No active business location in database.');
        }

        $product = Product::where('business_id', $this->businessId)
            ->where('is_inactive', 0)
            ->where('not_for_selling', 0)
            ->first();

        if (empty($product)) {
            $this->markTestSkipped('No sellable product in database.');
        }

        $variation = Variation::where('product_id', $product->id)->whereNull('deleted_at')->first();
        if (empty($variation)) {
            $this->markTestSkipped('No variation for product.');
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$location->id],
            'default_fulfillment_location_id' => $location->id,
            'cod_enabled' => true,
            'shipping' => [
                'flat_rate' => 50,
                'free_shipping_threshold' => 0,
            ],
        ]);
        Cache::flush();

        VariationLocationDetails::updateOrCreate(
            ['variation_id' => $variation->id, 'location_id' => $location->id],
            [
                'product_id' => $product->id,
                'product_variation_id' => $variation->product_variation_id,
                'qty_available' => 50,
            ]
        );

        return compact('location', 'variation');
    }

    private function firstShippingRateId(int $variationId, int $locationId): string
    {
        app(\App\Services\Storefront\Shipping\ShippingLegacyMigrator::class)
            ->ensureDefaultZones($this->businessId);

        $quoted = app(\App\Services\Storefront\Shipping\ShippingQuoteService::class)->quote(
            $this->businessId,
            100,
            [['variation_id' => $variationId, 'quantity' => 1]],
            ['country' => 'EG', 'state' => 'C'],
            null,
            $locationId,
            'en',
            false
        );

        $rate = $quoted['available_rates'][0] ?? null;
        if (empty($rate['id'])) {
            $this->markTestSkipped('No shipping rates available for test.');
        }

        return $rate['id'];
    }

    private function createCoupon(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'business_id' => $this->businessId,
            'code' => 'TEST'.strtoupper(substr(uniqid(), -6)),
            'name' => 'Test coupon',
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
        $email = 'coupon_test_'.uniqid().'@example.com';
        $mobile = '+2010'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

        $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Coupon',
            'last_name' => 'Tester',
            'email' => $email,
            'mobile' => $mobile,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $login = $this->postJson('/api/storefront/v1/auth/login', [
            'login' => $email,
            'password' => 'password123',
        ])->assertOk();

        return [
            'token' => $login->json('data.token'),
            'contact_id' => (int) $login->json('data.contact.id'),
            'email' => $email,
        ];
    }

    public function test_validate_rejects_guest_without_auth(): void
    {
        $fixtures = $this->setupCheckoutFixtures();
        $coupon = $this->createCoupon();

        $this->postJson('/api/storefront/v1/coupons/validate', [
            'code' => $coupon->code,
            'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
        ])->assertStatus(422)
            ->assertJsonPath('errors.coupon_code.0', 'Sign in to apply a promo code.');
    }

    public function test_validate_rejects_invalid_code(): void
    {
        $fixtures = $this->setupCheckoutFixtures();
        $session = $this->registerAndLogin();

        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/coupons/validate', [
                'code' => 'NOTREAL',
                'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
            ])->assertStatus(422)
            ->assertJsonPath('errors.coupon_code.0', 'Invalid promo code.');
    }

    public function test_validate_returns_discount_for_percent_coupon(): void
    {
        $fixtures = $this->setupCheckoutFixtures();
        $coupon = $this->createCoupon(['discount_amount' => 10]);
        $session = $this->registerAndLogin();

        $response = $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/coupons/validate', [
                'code' => $coupon->code,
                'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.coupon.code', $coupon->code)
            ->assertJsonStructure(['data' => ['coupon_discount', 'subtotal', 'shipping', 'total']]);

        $this->assertGreaterThan(0, (float) $response->json('data.coupon_discount'));
    }

    public function test_cart_validate_applies_coupon_to_totals(): void
    {
        $fixtures = $this->setupCheckoutFixtures();
        $coupon = $this->createCoupon(['discount_amount' => 10]);
        $session = $this->registerAndLogin();

        $without = $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/cart/validate', [
                'location_id' => $fixtures['location']->id,
                'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
            ])->assertOk();

        $with = $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/cart/validate', [
                'location_id' => $fixtures['location']->id,
                'coupon_code' => $coupon->code,
                'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
            ])->assertOk();

        $baseTotal = (float) $without->json('data.total');
        $discountedTotal = (float) $with->json('data.total');

        $this->assertLessThan($baseTotal, $discountedTotal);
        $this->assertGreaterThan(0, (float) $with->json('data.coupon_discount'));
    }

    public function test_cart_validate_rejects_guest_coupon(): void
    {
        $fixtures = $this->setupCheckoutFixtures();
        $coupon = $this->createCoupon();

        $this->postJson('/api/storefront/v1/cart/validate', [
            'location_id' => $fixtures['location']->id,
            'coupon_code' => $coupon->code,
            'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
        ])->assertStatus(422)
            ->assertJsonPath('errors.coupon_code.0', 'Sign in to apply a promo code.');
    }

    public function test_checkout_records_redemption_and_discount(): void
    {
        Mail::fake();
        $fixtures = $this->setupCheckoutFixtures();
        $coupon = $this->createCoupon(['discount_amount' => 10]);
        $session = $this->registerAndLogin();

        $orderKey = 'SF-COUPON-'.uniqid();

        $response = $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/checkout', [
                'idempotency_key' => $orderKey,
                'location_id' => $fixtures['location']->id,
                'payment_method' => 'cod',
                'coupon_code' => $coupon->code,
                'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
                'customer' => [
                    'first_name' => 'Coupon',
                    'last_name' => 'Buyer',
                    'email' => $session['email'],
                ],
                'shipping_address' => [
                    'address_line_1' => '1 Coupon St',
                    'city' => 'Cairo',
                    'state' => 'C',
                    'country' => 'EG',
                ],
                'shipping_rate_id' => $this->firstShippingRateId($fixtures['variation']->id, $fixtures['location']->id),
            ]);

        $response->assertCreated();

        $transaction = Transaction::where('storefront_order_id', $orderKey)->first();
        $this->assertNotNull($transaction);
        $this->assertSame($coupon->id, (int) $transaction->storefront_coupon_id);
        $this->assertSame($coupon->code, $transaction->storefront_coupon_code);
        $this->assertGreaterThan(0, (float) $transaction->discount_amount);

        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_id' => $coupon->id,
            'transaction_id' => $transaction->id,
        ]);

        $coupon->refresh();
        $this->assertSame(1, (int) $coupon->times_used);
    }

    public function test_checkout_rejects_guest_coupon(): void
    {
        Mail::fake();
        $fixtures = $this->setupCheckoutFixtures();
        $coupon = $this->createCoupon();

        $this->postJson('/api/storefront/v1/checkout', [
            'idempotency_key' => 'SF-GUEST-COUPON-'.uniqid(),
            'location_id' => $fixtures['location']->id,
            'payment_method' => 'cod',
            'coupon_code' => $coupon->code,
            'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
            'customer' => [
                'first_name' => 'Guest',
                'last_name' => 'Buyer',
                'email' => 'guest_coupon_'.uniqid().'@example.com',
            ],
            'shipping_address' => [
                'address_line_1' => '1 Guest St',
                'city' => 'Cairo',
                'state' => 'C',
                'country' => 'EG',
            ],
            'shipping_rate_id' => $this->firstShippingRateId($fixtures['variation']->id, $fixtures['location']->id),
        ])->assertStatus(422)
            ->assertJsonPath('errors.coupon_code.0', 'Sign in to apply a promo code.');
    }

    public function test_idempotent_checkout_does_not_double_redeem(): void
    {
        Mail::fake();
        $fixtures = $this->setupCheckoutFixtures();
        $coupon = $this->createCoupon(['discount_amount' => 5]);
        $session = $this->registerAndLogin();

        $payload = [
            'idempotency_key' => 'SF-COUPON-IDEM-'.uniqid(),
            'location_id' => $fixtures['location']->id,
            'payment_method' => 'cod',
            'coupon_code' => $coupon->code,
            'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
            'customer' => [
                'first_name' => 'Idem',
                'last_name' => 'Coupon',
                'email' => $session['email'],
            ],
            'shipping_address' => [
                'address_line_1' => '2 Idem Rd',
                'city' => 'Cairo',
                'state' => 'C',
                'country' => 'EG',
            ],
            'shipping_rate_id' => $this->firstShippingRateId($fixtures['variation']->id, $fixtures['location']->id),
        ];

        $this->withToken($session['token'])->postJson('/api/storefront/v1/checkout', $payload)->assertCreated();
        $this->withToken($session['token'])->postJson('/api/storefront/v1/checkout', $payload)->assertCreated();

        $transaction = Transaction::where('storefront_order_id', $payload['idempotency_key'])->first();
        $this->assertSame(
            1,
            CouponRedemption::where('coupon_id', $coupon->id)->where('transaction_id', $transaction->id)->count()
        );

        $coupon->refresh();
        $this->assertSame(1, (int) $coupon->times_used);
    }

    public function test_free_shipping_coupon_zeros_shipping(): void
    {
        $fixtures = $this->setupCheckoutFixtures();
        $coupon = $this->createCoupon([
            'type' => Coupon::TYPE_FREE_SHIPPING,
            'discount_amount' => 0,
        ]);
        $session = $this->registerAndLogin();

        $response = $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/cart/validate', [
                'location_id' => $fixtures['location']->id,
                'coupon_code' => $coupon->code,
                'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.shipping', 0);
    }

    public function test_rejects_reward_points_when_stacking_disabled(): void
    {
        Mail::fake();
        $fixtures = $this->setupCheckoutFixtures();
        $coupon = $this->createCoupon([
            'stack_with_reward_points' => false,
        ]);

        $business = Business::find($this->businessId);
        $business->enable_rp = 1;
        $business->redeem_amount_per_unit_rp = 0.1;
        $business->min_redeem_point = 1;
        $business->save();

        $email = 'nostack_'.uniqid().'@example.com';
        $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'No',
            'last_name' => 'Stack',
            'email' => $email,
            'mobile' => '+2010'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $login = $this->postJson('/api/storefront/v1/auth/login', [
            'login' => $email,
            'password' => 'password123',
        ])->assertOk();

        $contactId = (int) $login->json('data.contact.id');
        Contact::where('id', $contactId)->update(['total_rp' => 500]);

        $this->withToken($login->json('data.token'))
            ->postJson('/api/storefront/v1/checkout', [
                'idempotency_key' => 'SF-NOSTACK-'.uniqid(),
                'location_id' => $fixtures['location']->id,
                'payment_method' => 'cod',
                'coupon_code' => $coupon->code,
                'reward_points' => 100,
                'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
                'shipping_address' => [
                    'address_line_1' => '3 Stack Rd',
                    'city' => 'Cairo',
                    'state' => 'C',
                    'country' => 'EG',
                ],
                'shipping_rate_id' => $this->firstShippingRateId($fixtures['variation']->id, $fixtures['location']->id),
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.reward_points.0', 'Reward points cannot be combined with this promo code.');
    }

    public function test_rejects_coupon_when_disabled_at_checkout(): void
    {
        $fixtures = $this->setupCheckoutFixtures();
        $coupon = $this->createCoupon();
        $session = $this->registerAndLogin();

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$fixtures['location']->id],
            'default_fulfillment_location_id' => $fixtures['location']->id,
            'promo_codes' => [
                'enabled_at_checkout' => false,
                'allow_stacking' => false,
            ],
        ]);
        Cache::flush();

        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/coupons/validate', [
                'code' => $coupon->code,
                'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.coupon_code.0', 'Promo codes are not available at checkout.');
    }

    public function test_rejects_multiple_coupons_when_stacking_disabled(): void
    {
        $fixtures = $this->setupCheckoutFixtures();
        $couponA = $this->createCoupon(['discount_amount' => 5]);
        $couponB = $this->createCoupon(['discount_amount' => 5]);
        $session = $this->registerAndLogin();

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$fixtures['location']->id],
            'default_fulfillment_location_id' => $fixtures['location']->id,
            'promo_codes' => [
                'enabled_at_checkout' => true,
                'allow_stacking' => false,
            ],
        ]);
        Cache::flush();

        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/cart/validate', [
                'location_id' => $fixtures['location']->id,
                'coupon_codes' => [$couponA->code, $couponB->code],
                'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.coupon_code.0', 'Only one promo code can be applied per order.');
    }

    public function test_allows_stacked_coupons_when_enabled(): void
    {
        $fixtures = $this->setupCheckoutFixtures();
        $couponA = $this->createCoupon(['discount_amount' => 5]);
        $couponB = $this->createCoupon(['discount_amount' => 5]);
        $session = $this->registerAndLogin();

        app(StorefrontSettingService::class)->save($this->businessId, [
            'selling_location_ids' => [$fixtures['location']->id],
            'default_fulfillment_location_id' => $fixtures['location']->id,
            'promo_codes' => [
                'enabled_at_checkout' => true,
                'allow_stacking' => true,
            ],
        ]);
        Cache::flush();

        $single = $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/cart/validate', [
                'location_id' => $fixtures['location']->id,
                'coupon_code' => $couponA->code,
                'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
            ])->assertOk();

        $stacked = $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/cart/validate', [
                'location_id' => $fixtures['location']->id,
                'coupon_codes' => [$couponA->code, $couponB->code],
                'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
            ])->assertOk();

        $this->assertLessThan(
            (float) $single->json('data.total'),
            (float) $stacked->json('data.total')
        );
        $this->assertCount(2, $stacked->json('data.coupons'));
    }

    public function test_available_lists_eligible_coupons_for_authenticated_customer(): void
    {
        $fixtures = $this->setupCheckoutFixtures();
        $eligible = $this->createCoupon([
            'code' => 'PICKER'.strtoupper(substr(uniqid(), -6)),
            'discount_amount' => 10,
            'min_order_subtotal' => 0,
        ]);
        $inactive = $this->createCoupon([
            'code' => 'INACT'.strtoupper(substr(uniqid(), -6)),
            'is_active' => false,
        ]);
        $session = $this->registerAndLogin();

        $response = $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/coupons/available', [
                'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $codes = collect($response->json('data.coupons'))->pluck('code');
        $this->assertTrue($codes->contains($eligible->code));
        $this->assertFalse($codes->contains($inactive->code));

        $match = collect($response->json('data.coupons'))->firstWhere('code', $eligible->code);
        $this->assertNotNull($match);
        $this->assertGreaterThan(0, (float) $match['total_savings']);
    }

    public function test_available_excludes_coupons_below_min_subtotal(): void
    {
        $fixtures = $this->setupCheckoutFixtures();
        $tooHigh = $this->createCoupon([
            'code' => 'HIGH'.strtoupper(substr(uniqid(), -6)),
            'min_order_subtotal' => 999999,
        ]);
        $session = $this->registerAndLogin();

        $response = $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/coupons/available', [
                'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
            ]);

        $response->assertOk();
        $codes = collect($response->json('data.coupons'))->pluck('code');
        $this->assertFalse($codes->contains($tooHigh->code));
    }

    public function test_available_requires_auth(): void
    {
        $fixtures = $this->setupCheckoutFixtures();
        $this->createCoupon();

        $this->postJson('/api/storefront/v1/coupons/available', [
            'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
        ])->assertStatus(422)
            ->assertJsonPath('errors.coupon_code.0', 'Sign in to apply a promo code.');
    }

    public function test_available_returns_empty_when_checkout_promos_disabled(): void
    {
        $fixtures = $this->setupCheckoutFixtures();
        $coupon = $this->createCoupon(['code' => 'DISAB'.strtoupper(substr(uniqid(), -6))]);
        $session = $this->registerAndLogin();

        app(StorefrontSettingService::class)->save($this->businessId, [
            'promo_codes' => [
                'enabled_at_checkout' => false,
                'allow_stacking' => false,
            ],
        ]);
        Cache::flush();

        $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/coupons/available', [
                'items' => [['variation_id' => $fixtures['variation']->id, 'quantity' => 1]],
            ])
            ->assertOk()
            ->assertJsonPath('data.coupons', []);
    }
}
