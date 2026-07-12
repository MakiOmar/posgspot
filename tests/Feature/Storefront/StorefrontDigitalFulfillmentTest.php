<?php

namespace Tests\Feature\Storefront;

use App\Services\Storefront\Accounts\AccountsApiClient;
use App\Services\Storefront\DigitalFulfillmentService;
use App\StorefrontDigitalFulfillment;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * Digital secrets allocate only after payment; ledger tracks the Accounts trip.
 */
class StorefrontDigitalFulfillmentTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('storefront_digital_fulfillments')) {
            $this->markTestSkipped('storefront_digital_fulfillments table missing — run migrations.');
        }
    }

    public function test_fulfillment_skipped_when_not_paid(): void
    {
        $transaction = $this->makeStorefrontTransaction('due');
        $this->queuePendingRow($transaction);

        $accounts = Mockery::mock(AccountsApiClient::class);
        $accounts->shouldNotReceive('receiveOrder');
        $this->app->instance(AccountsApiClient::class, $accounts);

        app(DigitalFulfillmentService::class)->fulfillPaidTransaction($transaction->fresh(['contact', 'sell_lines']));

        $row = StorefrontDigitalFulfillment::where('transaction_id', $transaction->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status);
        $this->assertNull($row->secret_payload);
    }

    public function test_fulfillment_allocates_when_paid_and_stamps_accounts_order(): void
    {
        $transaction = $this->makeStorefrontTransaction('paid');
        $this->queuePendingRow($transaction);

        $accounts = Mockery::mock(AccountsApiClient::class);
        $accounts->shouldReceive('receiveOrder')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($transaction) {
                return ($payload['storefront_order_id'] ?? null) === $transaction->storefront_order_id
                    && (int) ($payload['pos_transaction_id'] ?? 0) === (int) $transaction->id
                    && (int) ($payload['wc_order_id'] ?? 0) === (int) $transaction->id
                    && (int) ($payload['game_id'] ?? 0) === 42;
            }))
            ->andReturn([
                'success' => true,
                'status' => 200,
                'body' => [
                    'order_id' => 9001,
                    'account_details' => [
                        'email' => 'game@example.com',
                        'password' => 'secret-pass',
                    ],
                ],
                'error' => null,
            ]);
        $this->app->instance(AccountsApiClient::class, $accounts);

        app(DigitalFulfillmentService::class)->fulfillPaidTransaction($transaction->fresh(['contact', 'sell_lines']));

        $row = StorefrontDigitalFulfillment::where('transaction_id', $transaction->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('allocated', $row->status);
        $this->assertSame(9001, (int) $row->accounts_order_id);

        $transaction->refresh();
        $this->assertStringContainsString('game@example.com', (string) $transaction->staff_note);
        $this->assertStringContainsString('secret-pass', (string) $transaction->staff_note);

        $deliveries = app(DigitalFulfillmentService::class)
            ->customerDeliveriesForTransaction($transaction->fresh());
        $this->assertCount(1, $deliveries);
        $this->assertSame('game@example.com', $deliveries[0]['account_email']);
        $this->assertSame('secret-pass', $deliveries[0]['account_password']);
    }

    public function test_customer_deliveries_empty_until_paid(): void
    {
        $transaction = $this->makeStorefrontTransaction('due');
        StorefrontDigitalFulfillment::create([
            'business_id' => (int) $transaction->business_id,
            'transaction_id' => (int) $transaction->id,
            'storefront_order_id' => $transaction->storefront_order_id,
            'line_key' => 'ps4_primary_stock|game:42',
            'kind' => 'game',
            'status' => 'allocated',
            'request_meta' => ['title' => 'Test Game'],
            'secret_payload' => null,
            'attempts' => 1,
        ]);

        $deliveries = app(DigitalFulfillmentService::class)
            ->customerDeliveriesForTransaction($transaction);
        $this->assertSame([], $deliveries);
    }

    private function makeStorefrontTransaction(string $paymentStatus): Transaction
    {
        $existing = Transaction::where('business_id', 1)
            ->where('type', 'sell')
            ->whereNotNull('contact_id')
            ->orderByDesc('id')
            ->first();

        if (! $existing) {
            $this->markTestSkipped('No sell transaction available to clone for digital fulfillment test.');
        }

        $clone = $existing->replicate([
            'invoice_no',
            'storefront_order_id',
            'payment_status',
            'source',
        ]);
        $clone->invoice_no = 'SF-DIG-'.uniqid();
        $clone->storefront_order_id = 'sf-digital-'.uniqid();
        $clone->payment_status = $paymentStatus;
        $clone->source = 'storefront';
        $clone->save();

        return $clone;
    }

    private function queuePendingRow(Transaction $transaction): void
    {
        app(DigitalFulfillmentService::class)->queuePending($transaction, [[
            'kind' => 'game',
            'game_id' => 42,
            'type' => 'primary',
            'platform' => '4',
            'line_key' => 'ps4_primary_stock|game:42',
            'title' => 'Test Game',
            'price' => 100,
            'sell_line_id' => null,
        ]]);
    }
}
