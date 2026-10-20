<?php

namespace Tests\Feature;

use App\BusinessLocation;
use App\CashRegister;
use App\Contact;
use App\Product;
use App\Services\AccountsCatalogService;
use App\Services\Accounts\SalesOrderAccountsGuard;
use App\Services\Accounts\SalesOrderDigitalFulfillmentService;
use App\Services\Storefront\DigitalFulfillmentService;
use App\Services\TransactionContentKindService;
use App\StorefrontDigitalFulfillment;
use App\Transaction;
use App\TransactionSellLine;
use App\Unit;
use App\User;
use App\Utils\ProductUtil;
use App\Variation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Call-center sales orders selling synced Accounts products: search, save guards,
 * allocation (on save / on paid / retry), notes and the accounts_only | mixed | pos_only tag.
 */
class SalesOrderAccountsTest extends TestCase
{
    use DatabaseTransactions;

    private const BUSINESS_ID = 1;

    private int $receiveCalls = 0;

    private bool $accountsOutOfStock = false;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.accounts.base' => 'https://accounts.test',
            'services.accounts.sales_order_fulfillment' => true,
            // Sales-order-only mode; POS sell tests switch it on explicitly.
            'services.accounts.pos_sell' => false,
            'services.accounts.sales_order_store_profile_id' => 17,
            'services.accounts.legacy_pos_skus' => ['0139'],
            'services.accounts.legacy_product_ids' => [],
        ]);
        Cache::put('accounts_auth_token', 'test-token', 60);
        Http::fake(function (HttpRequest $request) {
            if (str_contains($request->url(), '/api/orders/receive')) {
                if ($this->accountsOutOfStock) {
                    return Http::response(['message' => 'No available account matches the specified criteria.'], 422);
                }
                $this->receiveCalls++;
                $n = $this->receiveCalls;
                if (! empty($request['card_category_id'])) {
                    return Http::response(['order_id' => 7000 + $n, 'code' => "CARD-{$n}"], 201);
                }

                return Http::response([
                    'order_id' => 7000 + $n,
                    'account_details' => ['email' => "acc{$n}@psn.test", 'password' => "pw{$n}"],
                ], 201);
            }
            if (str_contains($request->url(), '/api/login')) {
                // Saving through the controllers can clear the cached token.
                return Http::response(['token' => 'test-token'], 200);
            }

            return Http::response(['success' => true], 200);
        });
    }

    private function location(): int
    {
        $id = BusinessLocation::where('business_id', self::BUSINESS_ID)->value('id');
        if (! $id) {
            $this->markTestSkipped('No location for business 1.');
        }

        return (int) $id;
    }

    private function product(string $sku, string $name, int $notForSelling = 1): Product
    {
        $product = Product::create([
            'name' => $name,
            'business_id' => self::BUSINESS_ID,
            'type' => 'single',
            'unit_id' => Unit::where('business_id', self::BUSINESS_ID)->value('id'),
            'tax_type' => 'exclusive',
            'enable_stock' => 0,
            'alert_quantity' => 0,
            'sku' => $sku,
            'barcode_type' => 'C128',
            'created_by' => 1,
            'is_inactive' => 0,
            'not_for_selling' => $notForSelling,
        ]);
        app(ProductUtil::class)->createSingleProductVariation($product, $sku, 100, 100, 0, 100, 100);

        return $product;
    }

    private function gameSku(int $gameId, string $offer): string
    {
        return 'ACCOUNTS-GAME-'.$gameId.'-PS5-'.$offer;
    }

    private function contact(): Contact
    {
        return Contact::create([
            'business_id' => self::BUSINESS_ID,
            'type' => 'customer',
            'name' => 'Call Center Buyer',
            'mobile' => '0100'.random_int(1000000, 9999999),
            'email' => 'buyer@example.com',
            'contact_id' => 'CO-T'.random_int(100000, 999999),
            'created_by' => 1,
        ]);
    }

    private function salesOrder(array $attributes = []): Transaction
    {
        return Transaction::create(array_merge([
            'business_id' => self::BUSINESS_ID,
            'location_id' => $this->location(),
            'type' => 'sales_order',
            'status' => 'ordered',
            'contact_id' => $this->contact()->id,
            'invoice_no' => 'SO-T'.random_int(100000, 999999),
            'transaction_date' => now(),
            'total_before_tax' => 200,
            'final_total' => 200,
            'payment_status' => 'due',
            'created_by' => 1,
        ], $attributes));
    }

    private function line(Transaction $transaction, Product $product, float $qty = 1, array $extra = []): TransactionSellLine
    {
        return TransactionSellLine::create(array_merge([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'variation_id' => Variation::where('product_id', $product->id)->value('id'),
            'quantity' => $qty,
            'unit_price' => 100,
            'unit_price_inc_tax' => 100,
            'item_tax' => 0,
            'line_discount_amount' => 0,
        ], $extra));
    }

    private function fulfilment(): SalesOrderDigitalFulfillmentService
    {
        return app(SalesOrderDigitalFulfillmentService::class);
    }

    public function test_search_offers_accounts_products_on_sales_orders_only_and_never_offline(): void
    {
        $gameId = random_int(9000000, 9999999);
        $name = 'ZZSO Search '.$gameId;
        $primary = $this->product($this->gameSku($gameId, 'PRIMARY'), $name.' Primary');
        $offline = $this->product($this->gameSku($gameId, 'OFFLINE'), $name.' Offline');
        $util = app(ProductUtil::class);

        $plain = collect($util->filterProduct(self::BUSINESS_ID, $name, null, 0, null, [], ['name']))->pluck('product_id')->all();
        $this->assertSame([], $plain, 'Plain POS search must keep synced products hidden.');

        $forSalesOrder = collect($util->filterProduct(self::BUSINESS_ID, $name, null, 0, null, [], ['name'], false, 'like', true))
            ->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($primary->id, $forSalesOrder);
        $this->assertNotContains($offline->id, $forSalesOrder);
        $this->assertSame(1, (int) $primary->fresh()->not_for_selling, 'Storefront visibility flag must stay on.');
    }

    public function test_guard_rules_for_accounts_lines(): void
    {
        $gameId = random_int(9000000, 9999999);
        $primary = $this->product($this->gameSku($gameId, 'PRIMARY'), 'ZZSO Guard '.$gameId);
        $offline = $this->product($this->gameSku($gameId, 'OFFLINE'), 'ZZSO Guard Off '.$gameId);
        $guard = app(SalesOrderAccountsGuard::class);
        $qty = fn ($v) => (float) $v;
        $row = fn (Product $p, array $extra = []) => array_merge(['product_id' => $p->id, 'quantity' => '1'], $extra);

        $this->assertSame(__('lang_v1.sales_order_offline_not_allowed'), $guard->check(self::BUSINESS_ID, [$row($offline)], true, null, $qty));
        $this->assertSame(__('lang_v1.accounts_products_sales_order_only'), $guard->check(self::BUSINESS_ID, [$row($primary)], false, null, $qty));
        $this->assertNull($guard->check(self::BUSINESS_ID, [$row($primary)], true, null, $qty));
        // Editing an existing storefront / Accounts sell keeps its lines.
        $this->assertNull($guard->check(self::BUSINESS_ID, [$row($primary, ['transaction_sell_lines_id' => 1])], false, null, $qty));
        // Converting a sales order line into an invoice is allowed.
        $this->assertNull($guard->check(self::BUSINESS_ID, [$row($primary, ['so_line_id' => 1])], false, null, $qty));
    }

    public function test_store_rejects_offline_on_sales_order_and_accounts_on_plain_sell_before_saving(): void
    {
        $gameId = random_int(9000000, 9999999);
        $primary = $this->product($this->gameSku($gameId, 'PRIMARY'), 'ZZSO Store '.$gameId);
        $offline = $this->product($this->gameSku($gameId, 'OFFLINE'), 'ZZSO Store Off '.$gameId);
        $user = $this->loginUser();
        $before = Transaction::where('business_id', self::BUSINESS_ID)->max('id');
        $payload = fn (Product $p, array $extra) => array_merge([
            'is_direct_sale' => 1,
            'location_id' => $this->location(),
            'contact_id' => $this->contact()->id,
            'final_total' => 0,
            'products' => [['product_id' => $p->id, 'variation_id' => Variation::where('product_id', $p->id)->value('id'), 'quantity' => 1]],
        ], $extra);

        $this->actingAs($user)->withSession(['user.business_id' => self::BUSINESS_ID])
            ->from('/sells/create?sale_type=sales_order')
            ->post('/pos', $payload($offline, ['type' => 'sales_order', 'status' => 'ordered']))
            ->assertRedirect('/sells/create?sale_type=sales_order')
            ->assertSessionHas('status.msg', __('lang_v1.sales_order_offline_not_allowed'));

        $this->actingAs($user)->withSession(['user.business_id' => self::BUSINESS_ID])
            ->from('/sells/create')
            ->post('/pos', $payload($primary, ['status' => 'final']))
            ->assertSessionHas('status.msg', __('lang_v1.accounts_products_sales_order_only'));

        $this->assertSame($before, Transaction::where('business_id', self::BUSINESS_ID)->max('id'), 'Nothing may be saved.');
    }

    public function test_update_rejects_removing_an_allocated_line(): void
    {
        $gameId = random_int(9000000, 9999999);
        $primary = $this->product($this->gameSku($gameId, 'PRIMARY'), 'ZZSO Lock '.$gameId);
        $physical = $this->product('ZZSO-PHYS-L'.$gameId, 'ZZSO Physical L '.$gameId, 0);
        $so = $this->salesOrder(['is_direct_sale' => 1]);
        $line = $this->line($so, $primary);
        $this->fulfilment()->fulfilLines($so, [$line->id]);

        $this->actingAs($this->loginUser())->withSession(['user.business_id' => self::BUSINESS_ID])
            ->from('/sells/'.$so->id.'/edit')
            ->put('/pos/'.$so->id, [
                'status' => 'ordered',
                'location_id' => $so->location_id,
                'contact_id' => $so->contact_id,
                'final_total' => 100,
                'products' => [['product_id' => $physical->id, 'variation_id' => Variation::where('product_id', $physical->id)->value('id'), 'quantity' => 1]],
            ])
            ->assertSessionHas('status.msg', __('lang_v1.allocated_account_line_locked'));

        $this->assertNotNull(TransactionSellLine::find($line->id));
    }

    public function test_saving_a_sales_order_with_fetch_account_ticked_allocates_immediately(): void
    {
        $gameId = random_int(9000000, 9999999);
        $primary = $this->product($this->gameSku($gameId, 'PRIMARY'), 'ZZSO Save '.$gameId);
        $contact = $this->contact();
        $user = $this->loginUser();

        $this->actingAs($user)->withSession(['user.business_id' => self::BUSINESS_ID, 'user.id' => $user->id])
            ->from('/sells/create?sale_type=sales_order')
            ->post('/pos', [
                'is_direct_sale' => 1,
                'type' => 'sales_order',
                'status' => 'ordered',
                'location_id' => $this->location(),
                'contact_id' => $contact->id,
                'discount_type' => 'fixed',
                'discount_amount' => 0,
                'tax_rate_id' => null,
                'final_total' => 100,
                'products' => [[
                    'product_id' => $primary->id,
                    'variation_id' => Variation::where('product_id', $primary->id)->value('id'),
                    'quantity' => 1,
                    'unit_price' => 100,
                    'unit_price_inc_tax' => 100,
                    'item_tax' => 0,
                    'tax_id' => null,
                    'line_discount_type' => 'fixed',
                    'line_discount_amount' => 0,
                    'enable_stock' => 0,
                    'product_type' => 'single',
                    'fetch_account' => 1,
                ]],
            ])
            ->assertSessionHas('status.success', 1);

        $so = Transaction::where('contact_id', $contact->id)->where('type', 'sales_order')->latest('id')->firstOrFail();
        $this->assertSame(1, $this->receiveCalls);
        $this->assertStringContainsString('acc1@psn.test', (string) $so->staff_note);
        $this->assertSame('accounts_only', $so->content_kind);
        $this->assertStringContainsString(__('lang_v1.accounts_fetched', ['count' => 1]), (string) session('status.msg'));
    }

    public function test_pos_sell_switch_controls_search_and_guard(): void
    {
        $gameId = random_int(9000000, 9999999);
        $primary = $this->product($this->gameSku($gameId, 'PRIMARY'), 'ZZSO PosGuard '.$gameId);
        $offline = $this->product($this->gameSku($gameId, 'OFFLINE'), 'ZZSO PosGuard Off '.$gameId);
        $guard = app(SalesOrderAccountsGuard::class);
        $qty = fn ($v) => (float) $v;
        $posSearch = Request::create('/products/list', 'GET', ['include_accounts' => 1, 'accounts_for_sales_order' => 0]);
        $soSearch = Request::create('/products/list', 'GET', ['include_accounts' => 1, 'accounts_for_sales_order' => 1]);

        $this->assertFalse(AccountsCatalogService::includeInSellSearch($posSearch));
        $this->assertTrue(AccountsCatalogService::includeInSellSearch($soSearch));

        config(['services.accounts.pos_sell' => true]);
        $this->assertTrue(AccountsCatalogService::includeInSellSearch($posSearch));
        $this->assertFalse(AccountsCatalogService::includeInSellSearch(Request::create('/products/list', 'GET', [])));
        $this->assertNull($guard->check(self::BUSINESS_ID, [['product_id' => $primary->id, 'quantity' => '1']], false, null, $qty));
        $this->assertSame(
            __('lang_v1.sale_offline_not_allowed'),
            $guard->check(self::BUSINESS_ID, [['product_id' => $offline->id, 'quantity' => '1']], false, null, $qty)
        );
        $this->assertSame(1, (int) $primary->fresh()->not_for_selling, 'Storefront visibility flag must stay on.');
    }

    /**
     * Paid POS checkout of one Accounts line, with or without "Fetch account now" ticked.
     */
    private function posCheckout(User $user, Contact $contact, Product $product, bool $fetchAccount): \Illuminate\Testing\TestResponse
    {
        CashRegister::firstOrCreate([
            'business_id' => self::BUSINESS_ID,
            'location_id' => $this->location(),
            'user_id' => $user->id,
            'status' => 'open',
        ]);

        return $this->actingAs($user)
            ->withSession(['user.business_id' => self::BUSINESS_ID, 'user.id' => $user->id])
            ->postJson('/pos', [
                'status' => 'final',
                'location_id' => $this->location(),
                'contact_id' => $contact->id,
                'discount_type' => 'fixed',
                'discount_amount' => 0,
                'tax_rate_id' => null,
                'final_total' => 100,
                'payment' => [['amount' => 100, 'method' => 'cash']],
                'products' => [array_merge([
                    'product_id' => $product->id,
                    'variation_id' => Variation::where('product_id', $product->id)->value('id'),
                    'quantity' => 1,
                    'unit_price' => 100,
                    'unit_price_inc_tax' => 100,
                    'item_tax' => 0,
                    'tax_id' => null,
                    'line_discount_type' => 'fixed',
                    'line_discount_amount' => 0,
                    'enable_stock' => 0,
                    'product_type' => 'single',
                ], $fetchAccount ? ['fetch_account' => 1] : [])],
            ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('success', 1);
    }

    public function test_pos_checkout_without_fetch_account_ticked_fetches_nothing_even_when_paid(): void
    {
        config(['services.accounts.pos_sell' => true]);
        $gameId = random_int(9000000, 9999999);
        $primary = $this->product($this->gameSku($gameId, 'PRIMARY'), 'ZZSO PosNoTick '.$gameId);
        $contact = $this->contact();

        $this->posCheckout($this->loginUser(), $contact, $primary, false)
            ->assertJsonMissingPath('accounts_delivery');

        $sell = Transaction::where('contact_id', $contact->id)->where('type', 'sell')->latest('id')->firstOrFail();
        $this->assertSame('paid', $sell->payment_status);
        $this->assertSame(0, $this->receiveCalls, 'Unticked POS lines must never be fetched.');
        $this->assertFalse(StorefrontDigitalFulfillment::where('storefront_order_id', 'pos-'.$sell->id)->exists());
        $this->assertSame(0, $this->fulfilment()->retry($sell->id));
        $this->assertSame(0, $this->receiveCalls);
    }

    public function test_pos_checkout_with_fetch_account_ticked_allocates_and_returns_credentials(): void
    {
        config(['services.accounts.pos_sell' => true]);
        $gameId = random_int(9000000, 9999999);
        $primary = $this->product($this->gameSku($gameId, 'PRIMARY'), 'ZZSO PosSale '.$gameId);
        $contact = $this->contact();
        $user = $this->loginUser();

        $response = $this->posCheckout($user, $contact, $primary, true)
            ->assertJsonPath('accounts_delivery.0.account_email', 'acc1@psn.test')
            ->assertJsonPath('accounts_delivery.0.account_password', 'pw1');

        $sell = Transaction::where('contact_id', $contact->id)->where('type', 'sell')->latest('id')->firstOrFail();
        $this->assertSame('paid', $sell->payment_status);
        $this->assertSame(1, $this->receiveCalls, 'The ticked line must be allocated exactly once.');
        $this->assertStringContainsString('acc1@psn.test', (string) $sell->staff_note);
        $this->assertSame('allocated', StorefrontDigitalFulfillment::where('storefront_order_id', 'pos-'.$sell->id)->value('status'));
        $this->assertStringContainsString(__('lang_v1.accounts_fetched', ['count' => 1]), (string) $response->json('msg'));

        // Deleting the sale would orphan a handed-out account.
        $this->actingAs($user)->withSession(['user.business_id' => self::BUSINESS_ID, 'user.id' => $user->id])
            ->deleteJson('/pos/'.$sell->id, [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertJsonPath('msg', __('lang_v1.allocated_account_sale_locked'));
        $this->assertNotNull(Transaction::find($sell->id));
    }

    public function test_pos_sell_fetches_only_ticked_final_lines_and_skips_sales_order_copies(): void
    {
        config(['services.accounts.pos_sell' => true]);
        $gameId = random_int(9000000, 9999999);
        $primary = $this->product($this->gameSku($gameId, 'PRIMARY'), 'ZZSO PosWait '.$gameId);

        $draft = $this->salesOrder(['type' => 'sell', 'status' => 'draft', 'payment_status' => 'paid']);
        $draftLine = $this->line($draft, $primary);
        $this->assertSame(0, $this->fulfilment()->fulfilLines($draft, [$draftLine->id])['allocated']);

        // Paying never fetches on a POS sell; ticking the line does, even on credit.
        $credit = $this->salesOrder(['type' => 'sell', 'status' => 'final', 'payment_status' => 'paid']);
        $creditLine = $this->line($credit, $primary);
        $this->assertSame(0, $this->fulfilment()->handlePaid($credit)['allocated']);
        $credit->update(['payment_status' => 'due']);
        $this->assertSame(1, $this->fulfilment()->fulfilLines($credit, [$creditLine->id])['allocated']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/api/orders/receive')
            && $r['storefront_order_id'] === 'pos-'.$credit->id
            && str_contains((string) $r['notes'], 'POS sale '.$credit->invoice_no));

        $so = $this->salesOrder();
        $soLine = $this->line($so, $primary);
        $invoice = $this->salesOrder(['type' => 'sell', 'status' => 'final', 'payment_status' => 'paid']);
        $invoiceLine = $this->line($invoice, $primary, 1, ['so_line_id' => $soLine->id]);
        $this->assertSame(0, $this->fulfilment()->fulfilLines($invoice, [$invoiceLine->id])['allocated']);

        config(['services.accounts.pos_sell' => false]);
        $off = $this->salesOrder(['type' => 'sell', 'status' => 'final', 'payment_status' => 'paid']);
        $offLine = $this->line($off, $primary);
        $this->assertSame(0, $this->fulfilment()->fulfilLines($off, [$offLine->id])['allocated']);
        $this->assertSame(1, $this->receiveCalls);
    }

    private function loginUser(): User
    {
        $user = User::where('business_id', self::BUSINESS_ID)->where('allow_login', 1)->whereNotNull('username')->first();
        if (! $user) {
            $this->markTestSkipped('No login user for business 1.');
        }
        config(['constants.administrator_usernames' => $user->username]);

        return $user;
    }

    public function test_checked_line_allocates_on_save_per_unit_and_is_idempotent(): void
    {
        $gameId = random_int(9000000, 9999999);
        $primary = $this->product($this->gameSku($gameId, 'PRIMARY'), 'ZZSO Alloc '.$gameId);
        $so = $this->salesOrder();
        $line = $this->line($so, $primary, 2);

        $result = $this->fulfilment()->fulfilLines($so, [$line->id]);

        $this->assertSame(2, $result['allocated']);
        $this->assertSame(2, $this->receiveCalls);
        $staff = (string) $so->fresh()->staff_note;
        $this->assertStringContainsString('acc1@psn.test', $staff);
        $this->assertStringContainsString('acc2@psn.test', $staff);
        $this->assertSame([$line->id => 2], $this->fulfilment()->allocatedUnitsBySellLine($so->id));

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/api/orders/receive')
            && $r['storefront_order_id'] === 'so-'.$so->id
            && $r['pos_transaction_id'] === $so->id
            && $r['type'] === 'primary'
            && str_contains((string) $r['notes'], $so->invoice_no));

        $again = $this->fulfilment()->fulfilLines($so->fresh(), [$line->id]);
        $this->assertSame(0, $again['allocated']);
        $this->assertSame(2, $this->receiveCalls, 'Replays must not call Accounts again.');
    }

    public function test_unchecked_lines_wait_for_full_payment(): void
    {
        $gameId = random_int(9000000, 9999999);
        $primary = $this->product($this->gameSku($gameId, 'PRIMARY'), 'ZZSO Paid '.$gameId);
        $card = $this->product('ACCOUNTS-CARD-'.$gameId, 'ZZSO Card '.$gameId);
        $so = $this->salesOrder();
        $this->line($so, $primary);
        $this->line($so, $card);

        $this->assertSame(0, $this->fulfilment()->handlePaid($so)['allocated']);
        $this->assertSame(0, $this->receiveCalls);

        $so->update(['payment_status' => 'paid']);
        $this->assertSame(2, $this->fulfilment()->handlePaid($so)['allocated']);
        $this->assertSame(0, $this->fulfilment()->handlePaid($so)['allocated']);
        $this->assertSame(2, $this->receiveCalls);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/api/orders/receive')
            && (int) ($r['card_category_id'] ?? 0) === $gameId);
    }

    public function test_out_of_stock_marks_failed_with_staff_note_and_retry_recovers(): void
    {
        $gameId = random_int(9000000, 9999999);
        $primary = $this->product($this->gameSku($gameId, 'PRIMARY'), 'ZZSO Retry '.$gameId);
        $so = $this->salesOrder();
        $line = $this->line($so, $primary);

        $this->accountsOutOfStock = true;
        $result = $this->fulfilment()->fulfilLines($so, [$line->id]);
        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('ZZSO Retry '.$gameId, (string) $so->fresh()->staff_note);
        $this->assertSame('failed', StorefrontDigitalFulfillment::where('storefront_order_id', 'so-'.$so->id)->value('status'));

        $this->accountsOutOfStock = false;
        $this->assertSame(1, $this->fulfilment()->retry($so->id));
        $this->assertStringContainsString('acc1@psn.test', (string) $so->fresh()->staff_note);
    }

    public function test_credentials_reach_an_invoice_already_converted_from_the_sales_order(): void
    {
        $gameId = random_int(9000000, 9999999);
        $primary = $this->product($this->gameSku($gameId, 'PRIMARY'), 'ZZSO Convert '.$gameId);
        $so = $this->salesOrder(['payment_status' => 'paid']);
        $soLine = $this->line($so, $primary);
        $sell = $this->salesOrder(['type' => 'sell', 'status' => 'final', 'payment_status' => 'paid']);
        $sellLine = $this->line($sell, $primary, 1, ['so_line_id' => $soLine->id]);

        $this->fulfilment()->handlePaid($so);

        $this->assertStringContainsString('acc1@psn.test', (string) $sell->fresh()->staff_note);
        $this->assertStringContainsString('acc1@psn.test', (string) $sellLine->fresh()->sell_line_note);
    }

    public function test_ignored_orders_and_storefront_hook_never_touch_sales_orders(): void
    {
        $gameId = random_int(9000000, 9999999);
        $primary = $this->product($this->gameSku($gameId, 'PRIMARY'), 'ZZSO Ignore '.$gameId);
        $accountsOrder = $this->salesOrder(['custom_field_1' => 'account_order']);
        $line = $this->line($accountsOrder, $primary);
        $this->assertSame(0, $this->fulfilment()->fulfilLines($accountsOrder, [$line->id])['allocated']);

        $so = $this->salesOrder(['payment_status' => 'paid']);
        $this->line($so, $primary);
        StorefrontDigitalFulfillment::create([
            'business_id' => self::BUSINESS_ID,
            'transaction_id' => $so->id,
            'storefront_order_id' => 'so-'.$so->id,
            'line_key' => 'manual|n:1',
            'kind' => 'game',
            'status' => 'pending',
            'request_meta' => ['game_id' => $gameId, 'type' => 'primary', 'platform' => '5'],
        ]);
        app(DigitalFulfillmentService::class)->handleStorefrontBecamePaid($so);

        $this->assertSame(0, $this->receiveCalls);
        $this->assertSame('pending', StorefrontDigitalFulfillment::where('line_key', 'manual|n:1')->where('transaction_id', $so->id)->value('status'));
    }

    public function test_content_kind_classification_and_listing_filter(): void
    {
        $gameId = random_int(9000000, 9999999);
        $primary = $this->product($this->gameSku($gameId, 'PRIMARY'), 'ZZSO Kind '.$gameId);
        $physical = $this->product('ZZSO-PHYS-'.$gameId, 'ZZSO Physical '.$gameId, 0);
        $legacy = Product::where('business_id', self::BUSINESS_ID)->where('sku', '0139')->first()
            ?? $this->product('0139', 'Legacy placeholder');
        $kinds = app(TransactionContentKindService::class);

        $accountsOnly = $this->salesOrder();
        $this->line($accountsOnly, $primary);
        $mixed = $this->salesOrder(['contact_id' => $accountsOnly->contact_id]);
        $this->line($mixed, $primary);
        $this->line($mixed, $physical);
        $posOnly = $this->salesOrder(['contact_id' => $accountsOnly->contact_id]);
        $this->line($posOnly, $physical);
        $legacySell = $this->salesOrder(['type' => 'sell', 'status' => 'final']);
        $this->line($legacySell, $legacy);

        $this->assertSame('accounts_only', $kinds->refresh($accountsOnly->id));
        $this->assertSame('mixed', $kinds->refresh($mixed->id));
        $this->assertSame('pos_only', $kinds->refresh($posOnly->id));
        $this->assertSame('accounts_only', $kinds->refresh($legacySell->id));

        $kinds->refreshMany([$posOnly->id]);
        $this->assertSame('pos_only', $posOnly->fresh()->content_kind);

        $response = $this->actingAs($this->loginUser())
            ->withSession(['user.business_id' => self::BUSINESS_ID, 'business.id' => self::BUSINESS_ID])
            ->getJson('/sells?sale_type=sales_order&content_kind=mixed&customer_id='.$accountsOnly->contact_id.'&draw=1&start=0&length=50', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $invoiceNos = collect($response->json('data'))->pluck('invoice_no')->implode(' ');
        $this->assertStringContainsString($mixed->invoice_no, $invoiceNos);
        $this->assertStringNotContainsString($accountsOnly->invoice_no, $invoiceNos);
        $this->assertStringNotContainsString($posOnly->invoice_no, $invoiceNos);
    }
}
