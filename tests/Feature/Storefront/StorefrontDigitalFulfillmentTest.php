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
        $accounts->shouldReceive('stampPosOrder')
            ->atLeast()->once()
            ->with($transaction->storefront_order_id, (int) $transaction->id, 9001)
            ->andReturn(['success' => true]);
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

    public function test_concurrent_trigger_does_not_allocate_a_claimed_row_twice(): void
    {
        $transaction = $this->makeStorefrontTransaction('paid');
        $this->queuePendingCardRow($transaction);
        $service = null;

        $accounts = Mockery::mock(AccountsApiClient::class);
        $accounts->shouldReceive('receiveOrder')
            ->once()
            ->andReturnUsing(function () use (&$service, $transaction) {
                // Webhook and return URL land while the first Accounts call is in flight.
                $service->fulfillPaidTransaction($transaction->fresh(['contact', 'sell_lines']));

                return $this->cardSuccess('CODE-ONCE');
            });
        $accounts->shouldReceive('stampPosOrder')->andReturn(['success' => true]);
        $this->app->instance(AccountsApiClient::class, $accounts);

        $service = app(DigitalFulfillmentService::class);
        $allocated = $service->fulfillPaidTransaction($transaction->fresh(['contact', 'sell_lines']));

        $this->assertSame(1, $allocated);
        $row = $this->cardRow($transaction);
        $this->assertSame('allocated', $row->status);
        $this->assertSame(1, (int) $row->attempts);
    }

    public function test_supplier_pending_response_keeps_row_pending_until_a_later_success(): void
    {
        $transaction = $this->makeStorefrontTransaction('paid');
        $this->queuePendingCardRow($transaction);

        $accounts = Mockery::mock(AccountsApiClient::class);
        $accounts->shouldReceive('receiveOrder')
            ->twice()
            ->andReturn(
                [
                    'success' => true,
                    'status' => 202,
                    'body' => ['success' => false, 'pending' => true, 'message' => 'Supplier order in progress'],
                    'error' => null,
                ],
                $this->cardSuccess('CODE-LATER')
            );
        $accounts->shouldReceive('stampPosOrder')->andReturn(['success' => true]);
        $this->app->instance(AccountsApiClient::class, $accounts);

        $service = app(DigitalFulfillmentService::class);
        $this->assertSame(0, $service->fulfillPaidTransaction($transaction->fresh(['contact', 'sell_lines'])));

        $row = $this->cardRow($transaction);
        $this->assertSame('pending', $row->status);
        $this->assertSame(0, (int) $row->attempts);
        $this->assertSame('Supplier order in progress', $row->last_error);
        $this->assertNull($row->readSecrets());

        $this->assertSame(1, $service->fulfillPaidTransaction($transaction->fresh(['contact', 'sell_lines'])));
        $row = $this->cardRow($transaction);
        $this->assertSame('allocated', $row->status);
        $this->assertSame('CODE-LATER', $row->readSecrets()['code'] ?? null);
    }

    public function test_success_without_credentials_is_failed_not_allocated(): void
    {
        $transaction = $this->makeStorefrontTransaction('paid');
        $this->queuePendingCardRow($transaction);

        $accounts = Mockery::mock(AccountsApiClient::class);
        $accounts->shouldReceive('receiveOrder')->once()->andReturn($this->cardSuccess(null));
        $accounts->shouldNotReceive('stampPosOrder');
        $this->app->instance(AccountsApiClient::class, $accounts);

        app(DigitalFulfillmentService::class)->fulfillPaidTransaction($transaction->fresh(['contact', 'sell_lines']));

        $row = $this->cardRow($transaction);
        $this->assertSame('failed', $row->status);
        $this->assertSame(1, (int) $row->attempts);
    }

    public function test_attempts_cap_stops_automatic_retries_but_not_staff_retry(): void
    {
        $transaction = $this->makeStorefrontTransaction('paid');
        $this->queuePendingCardRow($transaction);
        $this->cardRow($transaction)->forceFill([
            'status' => 'failed',
            'attempts' => DigitalFulfillmentService::MAX_ATTEMPTS,
        ])->save();

        $accounts = Mockery::mock(AccountsApiClient::class);
        $accounts->shouldReceive('receiveOrder')->once()->andReturn($this->cardSuccess('CODE-STAFF'));
        $accounts->shouldReceive('stampPosOrder')->andReturn(['success' => true]);
        $this->app->instance(AccountsApiClient::class, $accounts);

        $service = app(DigitalFulfillmentService::class);
        $this->assertSame(0, $service->fulfillPaidTransaction($transaction->fresh(['contact', 'sell_lines'])));
        $this->assertSame('failed', $this->cardRow($transaction)->status);

        $this->artisan('storefront:fulfill-digital', ['--recent' => true])->assertExitCode(0);
        $this->assertSame('failed', $this->cardRow($transaction)->status);

        $this->artisan('storefront:fulfill-digital', ['--transaction' => $transaction->id])->assertExitCode(0);
        $this->assertSame('allocated', $this->cardRow($transaction)->status);
    }

    public function test_abandoned_processing_row_is_reclaimed(): void
    {
        $transaction = $this->makeStorefrontTransaction('paid');
        $this->queuePendingCardRow($transaction);
        StorefrontDigitalFulfillment::whereKey($this->cardRow($transaction)->id)
            ->update(['status' => 'processing', 'updated_at' => now()->subMinutes(30)]);

        $accounts = Mockery::mock(AccountsApiClient::class);
        $accounts->shouldReceive('receiveOrder')->once()->andReturn($this->cardSuccess('CODE-STALE'));
        $accounts->shouldReceive('stampPosOrder')->andReturn(['success' => true]);
        $this->app->instance(AccountsApiClient::class, $accounts);

        $this->assertSame(1, app(DigitalFulfillmentService::class)
            ->fulfillPaidTransaction($transaction->fresh(['contact', 'sell_lines'])));
    }

    /**
     * @return array<string, mixed>
     */
    private function cardSuccess(?string $code): array
    {
        return [
            'success' => true,
            'status' => 200,
            'body' => ['success' => true, 'order_id' => 9100, 'code' => $code],
            'error' => null,
        ];
    }

    private function cardRow(Transaction $transaction): StorefrontDigitalFulfillment
    {
        return StorefrontDigitalFulfillment::where('transaction_id', $transaction->id)
            ->where('line_key', 'card|category:7')
            ->firstOrFail();
    }

    private function queuePendingCardRow(Transaction $transaction): void
    {
        app(DigitalFulfillmentService::class)->queuePending($transaction, [[
            'kind' => 'card',
            'card_category_id' => 7,
            'line_key' => 'card|category:7',
            'title' => 'Test Card',
            'price' => 50,
            'sell_line_id' => null,
        ]]);
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
