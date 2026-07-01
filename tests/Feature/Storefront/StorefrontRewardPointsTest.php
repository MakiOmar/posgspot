<?php

namespace Tests\Feature\Storefront;

use App\Business;
use App\Contact;
use Tests\TestCase;

/**
 * Storefront reward points API (balance, validate, checkout redemption).
 */
class StorefrontRewardPointsTest extends TestCase
{
    protected int $businessId = 1;

    private function enableRewardPoints(float $redeemAmountPerPoint = 0.1): void
    {
        $business = Business::find($this->businessId);
        $business->enable_rp = 1;
        $business->rp_name = 'Reward Points';
        $business->redeem_amount_per_unit_rp = $redeemAmountPerPoint;
        $business->min_redeem_point = 1;
        $business->max_redeem_point = null;
        $business->min_order_total_for_redeem = 0;
        $business->save();
    }

    private function registerAndLogin(): array
    {
        $email = 'rp_test_'.uniqid().'@example.com';
        $mobile = '01'.random_int(100000000, 999999999);

        $this->postJson('/api/storefront/v1/auth/register', [
            'first_name' => 'Reward',
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

    public function test_settings_exposes_reward_points_flag(): void
    {
        $this->enableRewardPoints();

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.reward_points.enabled', true)
            ->assertJsonPath('data.reward_points.name', 'Reward Points');
    }

    public function test_customer_can_fetch_reward_points_balance(): void
    {
        $this->enableRewardPoints(0.1);
        $session = $this->registerAndLogin();

        Contact::where('id', $session['contact_id'])->update([
            'total_rp' => 1000,
            'total_rp_used' => 50,
            'total_rp_expired' => 10,
        ]);

        $response = $this->withToken($session['token'])
            ->getJson('/api/storefront/v1/account/reward-points');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.available', 1000)
            ->assertJsonPath('data.used', 50)
            ->assertJsonPath('data.expired', 10)
            ->assertJsonPath('data.value', 100);
    }

    public function test_validate_reward_redeem_returns_discount_amount(): void
    {
        $this->enableRewardPoints(0.1);
        $session = $this->registerAndLogin();

        Contact::where('id', $session['contact_id'])->update(['total_rp' => 500]);

        $response = $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/account/reward-points/validate', [
                'requested_points' => 100,
                'order_total' => 200,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_valid', true)
            ->assertJsonPath('data.requested_points', 100)
            ->assertJsonPath('data.redeem_amount', 10);
    }

    public function test_validate_rejects_points_above_maximum(): void
    {
        $this->enableRewardPoints(0.1);
        $session = $this->registerAndLogin();

        Contact::where('id', $session['contact_id'])->update(['total_rp' => 50]);

        $response = $this->withToken($session['token'])
            ->postJson('/api/storefront/v1/account/reward-points/validate', [
                'requested_points' => 100,
                'order_total' => 200,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_valid', false);
    }

    public function test_reward_points_endpoints_require_authentication(): void
    {
        $this->getJson('/api/storefront/v1/account/reward-points')->assertUnauthorized();
        $this->postJson('/api/storefront/v1/account/reward-points/validate', [
            'requested_points' => 0,
            'order_total' => 100,
        ])->assertUnauthorized();
    }
}
