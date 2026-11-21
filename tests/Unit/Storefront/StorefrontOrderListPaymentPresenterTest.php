<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\StorefrontOrderListPaymentPresenter;
use App\Transaction;
use App\TransactionPayment;
use Tests\TestCase;

class StorefrontOrderListPaymentPresenterTest extends TestCase
{
    public function test_paid_geidea_order_with_capture_shows_gateway_and_completed_badge(): void
    {
        $transaction = new Transaction([
            'storefront_order_id' => 'SF-TEST-1',
            'storefront_payment_meta' => [
                'provider' => 'geidea',
                'geidea_order_id' => 'gid-1',
                'order_status' => 'Success',
                'detailed_status' => 'Paid',
            ],
            'payment_status' => 'paid',
        ]);
        $transaction->setRelation('payment_lines', collect([
            new TransactionPayment(['method' => 'card', 'payment_line_status' => 'completed', 'amount' => 100]),
        ]));

        $presenter = app(StorefrontOrderListPaymentPresenter::class);

        $this->assertSame('Geidea', $presenter->resolvePaymentMethodLabel($transaction, ['card']));
        $html = $presenter->listBadgeHtml($transaction);
        $this->assertStringContainsString('storefront-gateway-payment-label', $html);
        $this->assertStringContainsString('data-provider="geidea"', $html);
        $this->assertStringContainsString('data-capture-verified="1"', $html);
        $this->assertStringContainsString(__('restaurant.completed'), $html);
        $this->assertStringContainsString('bg-green', $html);
    }

    public function test_pending_geidea_order_shows_pending_badge(): void
    {
        $transaction = new Transaction([
            'storefront_order_id' => 'SF-TEST-2',
            'storefront_payment_meta' => ['provider' => 'geidea'],
            'payment_status' => 'due',
        ]);
        $transaction->setRelation('payment_lines', collect([
            new TransactionPayment(['method' => 'card', 'payment_line_status' => 'pending', 'amount' => 50]),
        ]));

        $presenter = app(StorefrontOrderListPaymentPresenter::class);
        $html = $presenter->listBadgeHtml($transaction);

        $this->assertStringContainsString(__('lang_v1.pending'), $html);
        $this->assertStringContainsString('bg-yellow', $html);
        $this->assertStringContainsString('data-capture-verified="0"', $html);
        $this->assertStringContainsString(
            'Awaiting',
            $presenter->resolvePaymentMethodLabel($transaction, ['card'])
        );
    }

    public function test_staff_marked_paid_without_capture_is_not_green_geidea(): void
    {
        $transaction = new Transaction([
            'storefront_order_id' => 'SF-TEST-3',
            'storefront_payment_meta' => ['provider' => 'geidea', 'session_id' => 'sess-1'],
            'payment_status' => 'paid',
        ]);
        $transaction->setRelation('payment_lines', collect([
            new TransactionPayment(['method' => 'card', 'payment_line_status' => 'completed', 'amount' => 100]),
        ]));

        $presenter = app(StorefrontOrderListPaymentPresenter::class);
        $html = $presenter->listBadgeHtml($transaction);

        $this->assertStringContainsString('bg-red', $html);
        $this->assertStringContainsString('data-capture-verified="0"', $html);
        $this->assertStringNotContainsString('bg-green', $html);
        $this->assertStringContainsString(
            'Awaiting',
            $presenter->resolvePaymentMethodLabel($transaction, ['card'])
        );
    }

    public function test_non_storefront_order_has_no_badge(): void
    {
        $transaction = new Transaction([
            'payment_status' => 'paid',
        ]);
        $transaction->setRelation('payment_lines', collect([
            new TransactionPayment(['method' => 'cash', 'payment_line_status' => 'completed', 'amount' => 10]),
        ]));

        $presenter = app(StorefrontOrderListPaymentPresenter::class);

        $this->assertSame('', $presenter->listBadgeHtml($transaction));
    }
}
