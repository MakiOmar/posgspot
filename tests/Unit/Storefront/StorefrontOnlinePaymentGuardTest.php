<?php

namespace Tests\Unit\Storefront;

use App\Exceptions\StorefrontGatewayPaymentBlockedException;
use App\Services\Storefront\Payment\StorefrontOnlinePaymentGuard;
use App\Transaction;
use App\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class StorefrontOnlinePaymentGuardTest extends TestCase
{
    public function test_blocks_completing_geidea_pending_without_capture(): void
    {
        $tx = new Transaction([
            'storefront_order_id' => 'web-test-1',
            'storefront_payment_meta' => ['provider' => 'geidea', 'session_id' => 's1'],
            'payment_status' => 'due',
        ]);
        $tx->id = 1;

        $guard = app(StorefrontOnlinePaymentGuard::class);

        $this->expectException(StorefrontGatewayPaymentBlockedException::class);
        $guard->assertMayCompleteOnlinePayment($tx, 'completed', 'pending');
    }

    public function test_allows_completing_when_geidea_capture_verified(): void
    {
        $tx = new Transaction([
            'storefront_order_id' => 'web-test-2',
            'storefront_payment_meta' => [
                'provider' => 'geidea',
                'geidea_order_id' => 'gid-9',
                'order_status' => 'Success',
                'detailed_status' => 'Paid',
            ],
            'payment_status' => 'due',
        ]);
        $tx->id = 2;

        $guard = app(StorefrontOnlinePaymentGuard::class);
        $guard->assertMayCompleteOnlinePayment($tx, 'completed', 'pending');
        $this->assertTrue($guard->hasVerifiedCapture($tx));
    }

    public function test_allows_already_completed_line_edits(): void
    {
        $tx = new Transaction([
            'storefront_order_id' => 'web-test-3',
            'storefront_payment_meta' => ['provider' => 'geidea'],
        ]);
        $tx->id = 3;

        app(StorefrontOnlinePaymentGuard::class)
            ->assertMayCompleteOnlinePayment($tx, 'completed', 'completed');
        $this->assertTrue(true);
    }

    public function test_override_permission_with_reason_allows_completion(): void
    {
        $user = $this->createMock(User::class);
        $user->method('can')
            ->with(StorefrontOnlinePaymentGuard::OVERRIDE_PERMISSION)
            ->willReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        Auth::shouldReceive('id')->andReturn(99);

        $tx = new Transaction([
            'storefront_order_id' => 'web-test-4',
            'invoice_no' => 'INV-4',
            'storefront_payment_meta' => ['provider' => 'geidea'],
            'payment_status' => 'due',
        ]);
        $tx->id = 4;

        app(StorefrontOnlinePaymentGuard::class)
            ->assertMayCompleteOnlinePayment($tx, 'completed', 'pending', 'Customer paid in cash at counter');
        $this->assertTrue(true);
    }

    public function test_non_storefront_sell_is_not_gated(): void
    {
        $tx = new Transaction([
            'payment_status' => 'due',
        ]);
        $tx->id = 5;

        app(StorefrontOnlinePaymentGuard::class)
            ->assertMayCompleteOnlinePayment($tx, 'completed', 'pending');
        $this->assertTrue(true);
    }
}
