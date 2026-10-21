<?php

namespace Tests\Feature;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\Product;
use App\Services\Accounts\SalesOrderDigitalFulfillmentService;
use App\StorefrontDigitalFulfillment;
use App\Transaction;
use App\TransactionSellLine;
use App\Unit;
use App\User;
use App\Utils\ProductUtil;
use App\Variation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Accounts undo -> POST /api/accounts/orders/undone/{business_id} unfetches the sales-order /
 * POS sell line so staff can fetch a new account.
 */
class AccountsOrderUndoneTest extends TestCase
{
    use DatabaseTransactions;

    private const BUSINESS_ID = 1;

    private int $receiveCalls = 0;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $business = Business::with('owner')->find(self::BUSINESS_ID);
        if (! $business || ! $business->owner) {
            $this->markTestSkipped('Business 1 with an owner is required.');
        }
        $this->owner = $business->owner;
        $this->usePassportTestKeys();

        config([
            'services.accounts.base' => 'https://accounts.test',
            'services.accounts.sales_order_fulfillment' => true,
            'services.accounts.pos_sell' => true,
            'services.accounts.sales_order_store_profile_id' => 17,
        ]);
        Cache::put('accounts_auth_token', 'test-token', 60);
        Http::fake(function (HttpRequest $request) {
            if (str_contains($request->url(), '/api/orders/receive')) {
                $n = ++$this->receiveCalls;
                if (! empty($request['card_category_id'])) {
                    return Http::response(['order_id' => 8000 + $n, 'code' => "CARD-{$n}"], 201);
                }

                return Http::response([
                    'order_id' => 8000 + $n,
                    'account_details' => ['email' => "undo{$n}@psn.test", 'password' => "pw{$n}"],
                ], 201);
            }

            return Http::response(['token' => 'test-token', 'success' => true], 200);
        });
    }

    /** Local installs may not have run passport:keys; the guard only needs a valid pair. */
    private function usePassportTestKeys(): void
    {
        $cnf = tempnam(sys_get_temp_dir(), 'ossl');
        file_put_contents($cnf, "[req]\ndistinguished_name=req_dn\n[req_dn]\n");
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $cnf];
        $key = openssl_pkey_new($options);
        if ($key === false) {
            @unlink($cnf);
            $this->markTestSkipped('OpenSSL cannot generate a test key.');
        }
        openssl_pkey_export($key, $private, null, $options);
        @unlink($cnf);
        config([
            'passport.private_key' => $private,
            'passport.public_key' => openssl_pkey_get_details($key)['key'],
        ]);
    }

    private function product(string $sku, string $name): Product
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
            'not_for_selling' => 1,
        ]);
        app(ProductUtil::class)->createSingleProductVariation($product, $sku, 100, 100, 0, 100, 100);

        return $product;
    }

    private function gameProduct(): Product
    {
        $gameId = random_int(9000000, 9999999);

        return $this->product('ACCOUNTS-GAME-'.$gameId.'-PS5-PRIMARY', 'ZZUndo Game '.$gameId);
    }

    private function transaction(array $attributes = []): Transaction
    {
        $locationId = BusinessLocation::where('business_id', self::BUSINESS_ID)->value('id');
        if (! $locationId) {
            $this->markTestSkipped('No location for business 1.');
        }
        $contact = Contact::create([
            'business_id' => self::BUSINESS_ID,
            'type' => 'customer',
            'name' => 'Undo Buyer',
            'mobile' => '0100'.random_int(1000000, 9999999),
            'contact_id' => 'CO-U'.random_int(100000, 999999),
            'created_by' => 1,
        ]);

        return Transaction::create(array_merge([
            'business_id' => self::BUSINESS_ID,
            'location_id' => $locationId,
            'type' => 'sales_order',
            'status' => 'ordered',
            'contact_id' => $contact->id,
            'invoice_no' => 'SO-U'.random_int(100000, 999999),
            'transaction_date' => now(),
            'total_before_tax' => 100,
            'final_total' => 100,
            'payment_status' => 'due',
            'created_by' => 1,
        ], $attributes));
    }

    private function line(Transaction $transaction, Product $product, array $extra = []): TransactionSellLine
    {
        return TransactionSellLine::create(array_merge([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'variation_id' => Variation::where('product_id', $product->id)->value('id'),
            'quantity' => 1,
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

    private function allocatedRow(string $ref): StorefrontDigitalFulfillment
    {
        return StorefrontDigitalFulfillment::where('storefront_order_id', $ref)->where('status', 'allocated')->firstOrFail();
    }

    private function undo(StorefrontDigitalFulfillment $row, array $override = []): \Illuminate\Testing\TestResponse
    {
        Passport::actingAs($this->owner, [], 'api');

        return $this->postJson('/api/accounts/orders/undone/'.self::BUSINESS_ID, array_merge([
            'storefront_order_id' => $row->storefront_order_id,
            'storefront_line_key' => $row->line_key,
            'accounts_order_id' => (int) $row->accounts_order_id,
        ], $override));
    }

    public function test_undo_resets_sales_order_line_and_allows_refetch(): void
    {
        $so = $this->transaction();
        $line = $this->line($so, $this->gameProduct());
        $this->fulfilment()->fulfilLines($so, [$line->id]);
        $row = $this->allocatedRow('so-'.$so->id);
        $this->assertStringContainsString('undo1@psn.test', (string) $so->fresh()->staff_note);

        $this->undo($row)->assertOk()->assertJson(['success' => true, 'reset' => true]);

        $this->assertNull(StorefrontDigitalFulfillment::find($row->id));
        $this->assertSame([], $this->fulfilment()->allocatedUnitsBySellLine($so->id));
        $staff = (string) $so->fresh()->staff_note;
        $this->assertStringContainsString("Account: N/A\nPassword: N/A", $staff);
        $this->assertStringNotContainsString('Password: pw1', $staff);
        $this->assertStringContainsString('undo1@psn.test', $staff, 'The undone note names the old account.');
        $this->assertStringNotContainsString('pw1', (string) $line->fresh()->sell_line_note);

        // Not refetched on its own: only when staff fetch again.
        $this->assertSame(0, $this->fulfilment()->retry($so->id));
        $this->assertSame(1, $this->fulfilment()->fulfilLines($so->fresh(), [$line->id])['allocated']);
        $this->assertSame(2, $this->receiveCalls);
        $refetched = (string) $so->fresh()->staff_note;
        $this->assertStringContainsString("Account: undo2@psn.test\nPassword: pw2", $refetched);
        $this->assertStringNotContainsString("Account: N/A\nPassword: N/A", $refetched);
    }

    public function test_undo_resets_pos_sell_and_converted_invoice_notes(): void
    {
        $product = $this->gameProduct();
        $sell = $this->transaction(['type' => 'sell', 'status' => 'final', 'payment_status' => 'paid']);
        $sellLine = $this->line($sell, $product);
        $this->fulfilment()->fulfilLines($sell, [$sellLine->id]);
        $posRow = $this->allocatedRow('pos-'.$sell->id);

        $this->undo($posRow)->assertOk()->assertJsonPath('reset', true);
        $this->assertNull(StorefrontDigitalFulfillment::find($posRow->id));
        $this->assertStringNotContainsString('Password: pw1', (string) $sell->fresh()->staff_note);

        $so = $this->transaction(['payment_status' => 'paid']);
        $soLine = $this->line($so, $product);
        $invoice = $this->transaction(['type' => 'sell', 'status' => 'final', 'payment_status' => 'paid']);
        $invoiceLine = $this->line($invoice, $product, ['so_line_id' => $soLine->id]);
        $this->fulfilment()->handlePaid($so);
        $this->assertStringContainsString('pw2', (string) $invoiceLine->fresh()->sell_line_note);

        $this->undo($this->allocatedRow('so-'.$so->id))->assertOk()->assertJsonPath('reset', true);
        $this->assertStringNotContainsString('pw2', (string) $invoiceLine->fresh()->sell_line_note);
        $this->assertStringNotContainsString('Password: pw2', (string) $invoice->fresh()->staff_note);
    }

    public function test_repeat_and_stale_undo_are_no_ops(): void
    {
        $so = $this->transaction();
        $line = $this->line($so, $this->gameProduct());
        $this->fulfilment()->fulfilLines($so, [$line->id]);
        $row = $this->allocatedRow('so-'.$so->id);

        $this->undo($row, ['accounts_order_id' => (int) $row->accounts_order_id + 1])
            ->assertOk()->assertJsonPath('reset', false);
        $this->assertSame('allocated', $row->fresh()->status);

        $this->undo($row)->assertOk()->assertJsonPath('reset', true);
        $this->undo($row)->assertOk()->assertJsonPath('reset', false);
    }

    public function test_rejects_storefront_refs_other_businesses_and_guests(): void
    {
        $so = $this->transaction();
        $line = $this->line($so, $this->gameProduct());
        $this->fulfilment()->fulfilLines($so, [$line->id]);
        $row = $this->allocatedRow('so-'.$so->id);

        $this->postJson('/api/accounts/orders/undone/'.self::BUSINESS_ID, [
            'storefront_order_id' => $row->storefront_order_id,
            'storefront_line_key' => $row->line_key,
        ])->assertUnauthorized();

        $this->undo($row, ['storefront_order_id' => 'sf-123'])->assertStatus(422);

        Passport::actingAs($this->owner, [], 'api');
        $this->postJson('/api/accounts/orders/undone/999999', [
            'storefront_order_id' => $row->storefront_order_id,
            'storefront_line_key' => $row->line_key,
        ])->assertForbidden();

        $this->assertSame('allocated', $row->fresh()->status);
    }
}
