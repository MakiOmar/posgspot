<?php

namespace Tests\Feature;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * pos_settings.hide_invoiced_sales_orders drops fully invoiced sales orders from the Sales Order list.
 */
class SalesOrderHideInvoicedTest extends TestCase
{
    use DatabaseTransactions;

    private const BUSINESS_ID = 1;

    private function user(): User
    {
        $user = User::where('business_id', self::BUSINESS_ID)->where('allow_login', 1)->whereNotNull('username')->first();
        if (! $user) {
            $this->markTestSkipped('No login user for business 1.');
        }
        config(['constants.administrator_usernames' => $user->username]);

        return $user;
    }

    private function setHideInvoiced(bool $on): void
    {
        $business = Business::find(self::BUSINESS_ID);
        $settings = json_decode((string) $business->pos_settings, true) ?: [];
        $settings['hide_invoiced_sales_orders'] = $on ? 1 : 0;
        $business->pos_settings = json_encode($settings);
        $business->save();
    }

    private function transaction(int $contactId, array $attributes): Transaction
    {
        return Transaction::create(array_merge([
            'business_id' => self::BUSINESS_ID,
            'location_id' => BusinessLocation::where('business_id', self::BUSINESS_ID)->value('id'),
            'type' => 'sales_order',
            'status' => 'ordered',
            'contact_id' => $contactId,
            'invoice_no' => 'SO-H'.random_int(100000, 999999),
            'transaction_date' => now(),
            'final_total' => 10,
            'payment_status' => 'due',
            'created_by' => 1,
        ], $attributes));
    }

    private function listedInvoiceNos(User $user, int $contactId): string
    {
        return collect($this->actingAs($user)
            ->withSession(['user.business_id' => self::BUSINESS_ID, 'business.id' => self::BUSINESS_ID, 'user.id' => $user->id])
            ->getJson('/sells?sale_type=sales_order&customer_id='.$contactId.'&draw=1&start=0&length=50', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json('data'))->pluck('invoice_no')->implode(' ');
    }

    public function test_setting_hides_only_completed_orders_with_a_final_invoice(): void
    {
        $user = $this->user();
        $contactId = Contact::create([
            'business_id' => self::BUSINESS_ID,
            'type' => 'customer',
            'name' => 'Hide Invoiced Buyer',
            'mobile' => '0100'.random_int(1000000, 9999999),
            'contact_id' => 'CO-H'.random_int(100000, 999999),
            'created_by' => 1,
        ])->id;

        $invoiced = $this->transaction($contactId, ['status' => 'completed', 'payment_status' => 'paid']);
        $partial = $this->transaction($contactId, ['status' => 'partial']);
        $closedByHand = $this->transaction($contactId, ['status' => 'completed']);
        $open = $this->transaction($contactId, []);

        $this->transaction($contactId, [
            'type' => 'sell',
            'status' => 'final',
            'invoice_no' => 'INV-H'.random_int(100000, 999999),
            'sales_order_ids' => [$invoiced->id],
        ]);
        $this->transaction($contactId, [
            'type' => 'sell',
            'status' => 'final',
            'invoice_no' => 'INV-H'.random_int(100000, 999999),
            'sales_order_ids' => [(string) $partial->id],
        ]);

        $this->setHideInvoiced(false);
        $shown = $this->listedInvoiceNos($user, $contactId);
        foreach ([$invoiced, $partial, $closedByHand, $open] as $so) {
            $this->assertStringContainsString($so->invoice_no, $shown);
        }

        $this->setHideInvoiced(true);
        $shown = $this->listedInvoiceNos($user, $contactId);
        $this->assertStringNotContainsString($invoiced->invoice_no, $shown);
        foreach ([$partial, $closedByHand, $open] as $so) {
            $this->assertStringContainsString($so->invoice_no, $shown);
        }
    }
}
