<?php

namespace Tests\Feature\Storefront;

use App\Services\Storefront\Payment\StorefrontPaymentRecorder;
use App\Transaction;
use App\TransactionPayment;
use App\User;
use Tests\TestCase;

class StorefrontSellListPaymentTest extends TestCase
{
    protected int $businessId = 1;

    public function test_sells_list_shows_geidea_and_completed_after_online_payment(): void
    {
        $admin = User::where('business_id', $this->businessId)->first();
        if ($admin === null) {
            $this->markTestSkipped('No admin user for business 1.');
        }

        $order = $this->makeGeideaPaidOrder();

        $response = $this->actingAs($admin)->getJson(
            '/sells?source=storefront&length=100&draw=1',
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk();

        $payload = $response->json();
        $this->assertIsArray($payload['data'] ?? null);

        $row = collect($payload['data'])->first(
            fn ($r) => str_contains((string) ($r['invoice_no'] ?? ''), $order->invoice_no)
        );
        $this->assertNotNull($row, 'Expected sell row in datatable JSON');

        $methodsHtml = (string) ($row['payment_methods'] ?? '');
        $this->assertStringContainsString('Geidea', $methodsHtml);
        $this->assertStringContainsString('storefront-gateway-payment-label', $methodsHtml);
        $this->assertStringContainsString(__('restaurant.completed'), $methodsHtml);
    }

    private function makeGeideaPaidOrder(): Transaction
    {
        $existing = Transaction::where('business_id', $this->businessId)
            ->where('type', 'sell')
            ->whereNotNull('contact_id')
            ->orderByDesc('id')
            ->first();
        if ($existing === null) {
            $this->markTestSkipped('No sell transaction available to clone.');
        }

        $order = $existing->replicate(['invoice_no', 'storefront_order_id', 'storefront_staff_notified_at']);
        $order->invoice_no = 'SF-GEI-LIST-'.uniqid();
        $order->storefront_order_id = 'sf-gei-list-'.uniqid();
        $order->payment_status = 'due';
        $order->status = 'final';
        $order->source = 'storefront';
        $order->shipping_status = 'ordered';
        $order->transaction_date = now();
        $order->save();

        TransactionPayment::create([
            'transaction_id' => $order->id,
            'business_id' => $this->businessId,
            'amount' => (float) $order->final_total,
            'method' => 'card',
            'payment_line_status' => 'pending',
            'paid_on' => now(),
            'created_by' => 1,
        ]);

        $order->storefront_payment_meta = ['provider' => 'geidea', 'session_id' => 'sess-list-test'];
        $order->save();

        app(StorefrontPaymentRecorder::class)->markPaid($order->fresh(['payment_lines']), $this->businessId, [
            'provider' => 'geidea',
            'geidea_order_id' => 'gid-list-test',
        ]);

        return $order->fresh(['payment_lines']);
    }
}
