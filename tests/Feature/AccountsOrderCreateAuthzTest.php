<?php

namespace Tests\Feature;

use App\Business;
use App\BusinessLocation;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AccountsOrderCreateAuthzTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $business = Business::with('owner')->find(1);
        if (! $business || ! $business->owner) {
            $this->markTestSkipped('Business 1 with an owner is required.');
        }
        $this->owner = $business->owner;
        $this->usePassportTestKeys();
    }

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

    public function test_rejects_order_create_for_other_business(): void
    {
        Passport::actingAs($this->owner, ['accounts-orders'], 'api');
        $location = BusinessLocation::where('business_id', 1)->orderBy('id')->firstOrFail();

        $this->postJson('/api/accounts/orders/create/999999', [
            'id' => 1,
            'location_id' => $location->id,
            'total' => '10',
            'line_items' => [['id' => 1, 'quantity' => 1, 'total' => '10', 'name' => 'x']],
            'billing' => ['first_name' => 'A', 'phone' => '01000000000'],
        ])->assertStatus(403);
    }

    public function test_rejects_location_from_other_business(): void
    {
        Passport::actingAs($this->owner, ['accounts-orders'], 'api');

        $this->postJson('/api/accounts/orders/create/1', [
            'id' => 1,
            'location_id' => 999999999,
            'total' => '10',
            'line_items' => [['id' => 1, 'quantity' => 1, 'total' => '10', 'name' => 'x']],
            'billing' => ['first_name' => 'A', 'phone' => '01000000000'],
        ])->assertStatus(422);
    }
}
