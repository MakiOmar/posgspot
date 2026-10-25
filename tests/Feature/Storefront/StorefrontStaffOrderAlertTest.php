<?php

namespace Tests\Feature\Storefront;

use App\Business;
use App\BusinessLocation;
use App\Jobs\NotifyStaffOfStorefrontOrder;
use App\Notifications\StorefrontNewOrderNotification;
use App\Services\Storefront\Payment\StorefrontPaymentRecorder;
use App\Services\Storefront\StorefrontMailService;
use App\Services\Storefront\StorefrontSettingService;
use App\Services\Storefront\StorefrontStaffOrderAlert;
use App\StorefrontDigitalFulfillment;
use App\Transaction;
use App\User;
use App\Utils\Util;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Website orders alert branch staff (POS bell) and the orders inbox exactly once, and show up in the Website orders queue.
 */
class StorefrontStaffOrderAlertTest extends TestCase
{
    use DatabaseTransactions;

    private int $businessId = 1;

    private BusinessLocation $location;

    private BusinessLocation $otherLocation;

    protected function setUp(): void
    {
        parent::setUp();

        $locations = BusinessLocation::where('business_id', $this->businessId)->orderBy('id')->take(2)->get();
        if ($locations->count() < 2) {
            $this->markTestSkipped('Needs two business locations.');
        }
        [$this->location, $this->otherLocation] = [$locations[0], $locations[1]];

        // .env mailer that would swallow the message if business settings were ignored.
        config(['mail.default' => 'log']);
        Business::whereKey($this->businessId)->update(['email_settings' => json_encode([
            'mail_driver' => 'array',
            'mail_host' => 'smtp.business.test',
            'mail_port' => 587,
            'mail_username' => 'shop@business.test',
            'mail_password' => 'secret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'shop@business.test',
            'mail_from_name' => 'Business Shop',
            'use_superadmin_settings' => 0,
        ])]);
        $this->setOrdersInbox('orders@business.test');
    }

    public function test_cod_order_alerts_branch_sales_staff_and_orders_inbox(): void
    {
        $branchStaff = $this->staffWith(['sell.view', 'location.'.$this->location->id]);
        $directSeller = $this->staffWith(['direct_sell.view', 'location.'.$this->location->id]);
        $allLocations = $this->staffWith(['sell.view', 'access_all_locations']);
        $otherBranch = $this->staffWith(['sell.view', 'location.'.$this->otherLocation->id]);
        $noSalesAccess = $this->staffWith(['location.'.$this->location->id]);
        $transaction = $this->makeStorefrontOrder();

        $this->runAlert($transaction, StorefrontStaffOrderAlert::PAYMENT_COD);

        foreach ([$branchStaff, $directSeller, $allLocations] as $user) {
            $this->assertSame(1, $this->alertCount($user), $user->username.' should get the bell alert.');
        }
        foreach ([$otherBranch, $noSalesAccess] as $user) {
            $this->assertSame(0, $this->alertCount($user), $user->username.' must not get the bell alert.');
        }

        $messages = $this->sentMessages();
        $this->assertCount(1, $messages);
        $sent = $messages->first()->getOriginalMessage();
        $this->assertSame('orders@business.test', $sent->getTo()[0]->getAddress());
        $this->assertStringContainsString($transaction->invoice_no, $sent->getSubject());
        $this->assertStringContainsString('view_sell='.$transaction->id, $sent->getHtmlBody());
        $this->assertNotNull($transaction->fresh()->storefront_staff_notified_at);
    }

    public function test_order_is_alerted_only_once(): void
    {
        $staff = $this->staffWith(['sell.view', 'access_all_locations']);
        $transaction = $this->makeStorefrontOrder();

        $this->runAlert($transaction, StorefrontStaffOrderAlert::PAYMENT_ONLINE);
        $this->runAlert($transaction, StorefrontStaffOrderAlert::PAYMENT_ONLINE);

        $this->assertSame(1, $this->alertCount($staff));
        $this->assertCount(1, $this->sentMessages());
    }

    public function test_email_falls_back_to_contact_inbox(): void
    {
        $this->setOrdersInbox('');
        $transaction = $this->makeStorefrontOrder();

        $this->runAlert($transaction, StorefrontStaffOrderAlert::PAYMENT_COD);

        $sent = $this->sentMessages()->first()->getOriginalMessage();
        $this->assertSame('shop@business.test', $sent->getTo()[0]->getAddress());
    }

    public function test_mail_failure_still_sends_bell_and_does_not_throw(): void
    {
        $staff = $this->staffWith(['sell.view', 'access_all_locations']);
        $transaction = $this->makeStorefrontOrder();
        $this->mock(StorefrontMailService::class, function ($mock) {
            $mock->shouldReceive('ordersRecipient')->andReturn('orders@business.test');
            $mock->shouldReceive('sendNowForBusiness')->andThrow(new \RuntimeException('SMTP down'));
        });

        $this->runAlert($transaction, StorefrontStaffOrderAlert::PAYMENT_COD);

        $this->assertSame(1, $this->alertCount($staff));
    }

    public function test_pos_sales_are_never_alerted(): void
    {
        $staff = $this->staffWith(['sell.view', 'access_all_locations']);
        $transaction = $this->makeStorefrontOrder();
        Transaction::whereKey($transaction->id)->update(['source' => null]);

        $this->runAlert($transaction, StorefrontStaffOrderAlert::PAYMENT_COD);

        $this->assertSame(0, $this->alertCount($staff));
        $this->assertNull($transaction->fresh()->storefront_staff_notified_at);
    }

    public function test_summary_flags_digital_only_orders(): void
    {
        $transaction = $this->makeStorefrontOrder();
        $alert = app(StorefrontStaffOrderAlert::class);
        $this->assertFalse($alert->summary($transaction, StorefrontStaffOrderAlert::PAYMENT_ONLINE)['digital_only']);

        StorefrontDigitalFulfillment::create([
            'business_id' => $this->businessId,
            'transaction_id' => (int) $transaction->id,
            'storefront_order_id' => $transaction->storefront_order_id,
            'line_key' => 'card|category:7',
            'kind' => 'card',
            'status' => 'pending',
            'request_meta' => ['title' => 'Test Card'],
            'attempts' => 0,
        ]);

        $summary = $alert->summary($transaction->fresh(), StorefrontStaffOrderAlert::PAYMENT_ONLINE);
        $this->assertTrue($summary['digital_only']);
        $this->assertSame('online', $summary['payment']);
    }

    public function test_bell_message_links_to_the_order(): void
    {
        $staff = $this->staffWith(['sell.view', 'access_all_locations']);
        $transaction = $this->makeStorefrontOrder();
        $this->runAlert($transaction, StorefrontStaffOrderAlert::PAYMENT_COD);

        $parsed = app(Util::class)->parseNotifications($staff->fresh()->notifications);

        $this->assertCount(1, $parsed);
        $this->assertStringContainsString($transaction->invoice_no, $parsed[0]['msg']);
        $this->assertStringContainsString('view_sell='.$transaction->id, $parsed[0]['link']);
    }

    public function test_first_online_payment_queues_alert_and_repeat_does_not(): void
    {
        $transaction = $this->makeStorefrontOrder('due');
        Queue::fake();

        $recorder = app(StorefrontPaymentRecorder::class);
        $recorder->markPaid($transaction, $this->businessId);
        $recorder->markPaid($transaction->fresh(), $this->businessId);

        Queue::assertPushed(NotifyStaffOfStorefrontOrder::class, 1);
        Queue::assertPushed(NotifyStaffOfStorefrontOrder::class, fn ($job) => $job->transactionId === (int) $transaction->id
            && $job->payment === StorefrontStaffOrderAlert::PAYMENT_ONLINE);
    }

    public function test_website_orders_queue_lists_only_alerted_orders(): void
    {
        $admin = $this->admin();
        $alerted = $this->makeStorefrontOrder();
        $this->runAlert($alerted, StorefrontStaffOrderAlert::PAYMENT_COD);
        $unpaidOnline = $this->makeStorefrontOrder('due');

        $response = $this->actingAs($admin)->getJson(
            '/sells?website_orders=1&source=storefront&shipping_status=ordered&length=100',
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk();

        $response->assertSee($alerted->invoice_no)->assertDontSee($unpaidOnline->invoice_no);
    }

    public function test_sidebar_shows_website_orders_with_open_count(): void
    {
        $admin = $this->admin();
        $alerted = $this->makeStorefrontOrder();
        $this->runAlert($alerted, StorefrontStaffOrderAlert::PAYMENT_COD);
        Cache::flush();

        $expected = app(StorefrontStaffOrderAlert::class)->openCount($this->businessId, 'all');
        $this->assertGreaterThan(0, $expected);

        $this->actingAs($admin)->get('/sells?source=storefront&shipping_status=ordered&website_orders=1&view_sell='.$alerted->id)
            ->assertOk()
            ->assertSee('sells\/'.$alerted->id, false)
            ->assertSee(__('lang_v1.website_orders'))
            ->assertSee('website_orders=1', false)
            ->assertSee('<span class="label label-warning">'.$expected.'</span>', false);
    }

    private function runAlert(Transaction $transaction, string $payment): void
    {
        (new NotifyStaffOfStorefrontOrder((int) $transaction->id, $payment))
            ->handle(app(StorefrontStaffOrderAlert::class), app(StorefrontMailService::class));
    }

    private function alertCount(User $user): int
    {
        return $user->notifications()->where('type', StorefrontNewOrderNotification::class)->count();
    }

    private function sentMessages()
    {
        return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
    }

    private function setOrdersInbox(string $email): void
    {
        app(StorefrontSettingService::class)->save($this->businessId, ['orders' => ['notify_email' => $email]]);
        Cache::flush();
    }

    private function makeStorefrontOrder(string $paymentStatus = 'paid'): Transaction
    {
        $existing = Transaction::where('business_id', $this->businessId)
            ->where('type', 'sell')
            ->whereNotNull('contact_id')
            ->orderByDesc('id')
            ->first();
        if (! $existing) {
            $this->markTestSkipped('No sell transaction available to clone.');
        }

        $order = $existing->replicate(['invoice_no', 'storefront_order_id', 'storefront_staff_notified_at']);
        $order->invoice_no = 'SF-ALERT-'.uniqid();
        $order->storefront_order_id = 'sf-alert-'.uniqid();
        $order->payment_status = $paymentStatus;
        $order->status = 'final';
        $order->source = 'storefront';
        $order->shipping_status = 'ordered';
        $order->location_id = $this->location->id;
        $order->transaction_date = now();
        $order->save();

        return $order;
    }

    private function admin(): User
    {
        $admin = User::where('business_id', $this->businessId)
            ->get()
            ->first(fn (User $user) => $user->hasRole('Admin#'.$this->businessId));
        if (! $admin) {
            $this->markTestSkipped('No admin user for business 1.');
        }

        return $admin;
    }

    /** Active staff member holding only the given permissions through a custom role. */
    private function staffWith(array $permissions): User
    {
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = Role::create([
            'name' => 'ZZ Order Alert '.uniqid().'#'.$this->businessId,
            'business_id' => $this->businessId,
            'guard_name' => 'web',
        ]);
        // Branch access (location.N) is granted per user in POS, not through roles.
        $locationPermissions = array_values(array_filter($permissions, fn ($name) => str_starts_with($name, 'location.')));
        $role->syncPermissions(array_diff($permissions, $locationPermissions));

        // Explicit id, reloaded after insert: some local dumps lost AUTO_INCREMENT on users.id.
        $id = (int) User::max('id') + 1;
        User::forceCreate([
            'id' => $id,
            'user_type' => 'user',
            'surname' => '',
            'first_name' => 'ZZ Order',
            'last_name' => 'Alert',
            'username' => 'zz_order_alert_'.uniqid(),
            'email' => 'zz_order_alert_'.uniqid().'@example.test',
            'password' => Hash::make('secret'),
            'language' => 'en',
            'business_id' => $this->businessId,
            'status' => 'active',
            'allow_login' => 1,
        ]);
        $user = User::findOrFail($id);
        $user->assignRole($role->name);
        $user->givePermissionTo($locationPermissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
