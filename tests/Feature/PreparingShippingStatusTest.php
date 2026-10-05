<?php

namespace Tests\Feature;

use App\Transaction;
use App\User;
use App\Utils\Util;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * "Preparing" shipping status sits between Ordered and Packed and can be set from Edit shipping.
 */
class PreparingShippingStatusTest extends TestCase
{
    use DatabaseTransactions;

    public function test_preparing_is_listed_between_ordered_and_packed(): void
    {
        $keys = array_keys(app(Util::class)->shipping_statuses());

        $this->assertSame(['ordered', 'preparing', 'packed'], array_slice($keys, 0, 3));
        $this->assertSame('Preparing', __('lang_v1.preparing'));
    }

    public function test_edit_shipping_saves_preparing_and_shows_it_in_the_form(): void
    {
        $user = User::where('business_id', 1)->where('allow_login', 1)->whereNotNull('username')->first();
        $sell = Transaction::where('business_id', 1)->where('type', 'sell')->latest('id')->first();
        if (! $user || ! $sell) {
            $this->markTestSkipped('Needs a login user and a sell for business 1.');
        }
        config(['constants.administrator_usernames' => $user->username]);

        $this->actingAs($user)->get('/sells/edit-shipping/' . $sell->id, ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('value="preparing"', false);

        $this->actingAs($user)->putJson('/sells/update-shipping/' . $sell->id, ['shipping_status' => 'preparing'])
            ->assertOk()
            ->assertJsonPath('success', 1);

        $this->assertSame('preparing', $sell->fresh()->shipping_status);
    }
}
