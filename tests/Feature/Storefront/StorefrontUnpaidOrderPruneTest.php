<?php

namespace Tests\Feature\Storefront;

use App\Services\Storefront\StorefrontSettingService;
use App\Services\Storefront\StorefrontUnpaidOrderPruner;
use App\Transaction;
use App\TransactionPayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Abandoned unpaid online website checkouts are deleted after the configured waiting time.
 */
class StorefrontUnpaidOrderPruneTest extends TestCase
{
    use DatabaseTransactions;

    private int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        app(StorefrontSettingService::class)->save($this->businessId, [
            'orders' => [
                'unpaid_abandon_after_hours' => 24,
            ],
        ]);
        Cache::flush();
    }

    public function test_defaults_include_unpaid_abandon_after_hours(): void
    {
        $defaults = app(StorefrontSettingService::class)->defaults();
        $this->assertSame(24, $defaults['orders']['unpaid_abandon_after_hours']);
    }

    public function test_prunes_old_unpaid_online_storefront_orders(): void
    {
        $oldUnpaid = $this->makeUnpaidOnlineOrder(now()->subHours(30));
        $recentUnpaid = $this->makeUnpaidOnlineOrder(now()->subHours(2));
        $alertedCod = $this->makeCodAlertedOrder(now()->subHours(48));
        $paid = $this->makeUnpaidOnlineOrder(now()->subHours(48));
        $paid->payment_status = 'paid';
        $paid->storefront_staff_notified_at = now();
        $paid->save();
        TransactionPayment::where('transaction_id', $paid->id)
            ->update(['payment_line_status' => 'completed']);

        // Local DBs may already have hundreds of abandoned checkouts; keep only our fixture overdue.
        Transaction::query()
            ->where('business_id', $this->businessId)
            ->where('source', 'storefront')
            ->whereNull('storefront_staff_notified_at')
            ->whereIn('payment_status', ['due', 'failed'])
            ->where('id', '!=', $oldUnpaid->id)
            ->update(['created_at' => now()]);

        $result = app(StorefrontUnpaidOrderPruner::class)->prune($this->businessId, 24, 50, false);

        $this->assertSame(1, $result['candidates']);
        $this->assertSame(1, $result['deleted']);
        $this->assertNull(Transaction::find($oldUnpaid->id));
        $this->assertNotNull(Transaction::find($recentUnpaid->id));
        $this->assertNotNull(Transaction::find($alertedCod->id));
        $this->assertNotNull(Transaction::find($paid->id));
    }

    public function test_dry_run_does_not_delete(): void
    {
        $oldUnpaid = $this->makeUnpaidOnlineOrder(now()->subHours(30));

        $exit = Artisan::call('storefront:prune-unpaid-orders', [
            '--business_id' => $this->businessId,
            '--hours' => 24,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertNotNull(Transaction::find($oldUnpaid->id));
        $this->assertStringContainsString('dry-run', Artisan::output());
    }

    public function test_zero_hours_disables_prune(): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, [
            'orders' => ['unpaid_abandon_after_hours' => 0],
        ]);
        Cache::flush();

        $oldUnpaid = $this->makeUnpaidOnlineOrder(now()->subDays(10));
        $pruner = app(StorefrontUnpaidOrderPruner::class);
        $this->assertSame(0, $pruner->waitingHours($this->businessId));

        $result = $pruner->prune($this->businessId, 0, 50, false);
        $this->assertSame(0, $result['candidates']);
        $this->assertNotNull(Transaction::find($oldUnpaid->id));
    }

    public function test_missing_hours_key_uses_default_not_zero(): void
    {
        // Stale cache blobs from before this feature omit the key; that must not mean "disabled".
        Cache::put('storefront_settings_'.$this->businessId, [
            'orders' => ['notify_email' => 'orders@example.test'],
        ], 300);

        $this->assertSame(24, app(StorefrontUnpaidOrderPruner::class)->waitingHours($this->businessId));
    }

    private function makeUnpaidOnlineOrder($createdAt): Transaction
    {
        $order = $this->cloneBaseSell();
        $order->payment_status = 'due';
        $order->storefront_staff_notified_at = null;
        $order->created_at = $createdAt;
        $order->updated_at = $createdAt;
        $order->save();

        TransactionPayment::create([
            'transaction_id' => $order->id,
            'business_id' => $this->businessId,
            'amount' => (float) $order->final_total,
            'method' => 'card',
            'payment_line_status' => 'pending',
            'paid_on' => $createdAt,
            'created_by' => $order->created_by,
        ]);

        return $order->fresh();
    }

    private function makeCodAlertedOrder($createdAt): Transaction
    {
        $order = $this->cloneBaseSell();
        $order->payment_status = 'due';
        $order->storefront_staff_notified_at = $createdAt;
        $order->created_at = $createdAt;
        $order->updated_at = $createdAt;
        $order->save();

        return $order->fresh();
    }

    private function cloneBaseSell(): Transaction
    {
        $existing = Transaction::where('business_id', $this->businessId)
            ->where('type', 'sell')
            ->whereNotNull('contact_id')
            ->orderByDesc('id')
            ->first();
        if (! $existing) {
            $this->markTestSkipped('No sell transaction available to clone.');
        }

        $order = $existing->replicate([
            'invoice_no',
            'storefront_order_id',
            'storefront_staff_notified_at',
            'storefront_payment_meta',
        ]);
        $order->invoice_no = 'SF-PRUNE-'.uniqid();
        $order->storefront_order_id = 'sf-prune-'.uniqid();
        $order->status = 'final';
        $order->source = 'storefront';
        $order->shipping_status = 'ordered';
        $order->transaction_date = now();
        $order->save();

        return $order;
    }
}
