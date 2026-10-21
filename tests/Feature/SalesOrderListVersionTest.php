<?php

namespace Tests\Feature;

use App\BusinessLocation;
use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * GET /sales-orders/list-version lets the Sales Order list reload only when orders change.
 */
class SalesOrderListVersionTest extends TestCase
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

    private function version(User $user): string
    {
        return (string) $this->actingAs($user)
            ->withSession(['user.business_id' => self::BUSINESS_ID, 'user.id' => $user->id])
            ->getJson('/sales-orders/list-version', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json('version');
    }

    public function test_version_changes_when_a_sales_order_is_added_or_updated(): void
    {
        $user = $this->user();
        $before = $this->version($user);
        $this->assertNotSame('', $before);
        $this->assertSame($before, $this->version($user), 'Unchanged data keeps the same version.');

        $so = Transaction::create([
            'business_id' => self::BUSINESS_ID,
            'location_id' => BusinessLocation::where('business_id', self::BUSINESS_ID)->value('id'),
            'type' => 'sales_order',
            'status' => 'ordered',
            'contact_id' => \App\Contact::where('business_id', self::BUSINESS_ID)->value('id'),
            'invoice_no' => 'SO-V'.random_int(100000, 999999),
            'transaction_date' => now(),
            'final_total' => 10,
            'payment_status' => 'due',
            'created_by' => $user->id,
        ]);
        $afterCreate = $this->version($user);
        $this->assertNotSame($before, $afterCreate);

        $so->forceFill(['status' => 'completed', 'updated_at' => now()->addMinute()])->save();
        $this->assertNotSame($afterCreate, $this->version($user));
    }

    public function test_guests_are_redirected(): void
    {
        $this->get('/sales-orders/list-version')->assertRedirect();
    }
}
