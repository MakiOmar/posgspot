<?php

namespace Tests\Feature;

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ApiTokenLoginTest extends TestCase
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

    public function test_non_allowlisted_user_cannot_login(): void
    {
        config(['api.login_user_ids' => [], 'api.login_usernames' => ['__nobody__']]);
        $this->owner->password = Hash::make('secret-pass');
        $this->owner->status = 'active';
        $this->owner->allow_login = 1;
        $this->owner->user_type = 'user';
        $this->owner->save();

        $this->postJson('/api/login', [
            'username' => $this->owner->username,
            'password' => 'secret-pass',
        ])->assertStatus(403);
    }

    public function test_allowlisted_inactive_user_cannot_login(): void
    {
        config([
            'api.login_user_ids' => [(int) $this->owner->id],
            'api.login_usernames' => [],
        ]);
        $this->owner->password = Hash::make('secret-pass');
        $this->owner->status = 'inactive';
        $this->owner->allow_login = 1;
        $this->owner->user_type = 'user';
        $this->owner->save();

        $this->postJson('/api/login', [
            'username' => $this->owner->username,
            'password' => 'secret-pass',
        ])->assertStatus(403);
    }

    public function test_scoped_token_required_for_catalog(): void
    {
        Passport::actingAs($this->owner, ['user-read'], 'api');

        $this->postJson('/api/accounts/catalog/upsert/1', [
            'kind' => 'game',
            'items' => [['sku' => 'ACCOUNTS-GAME-1-PS5-PRIMARY', 'name' => 'X', 'price' => 1]],
        ])->assertStatus(403);
    }

    public function test_allowlisted_active_user_receives_scoped_token(): void
    {
        if (! \Laravel\Passport\Client::where('personal_access_client', true)->exists()) {
            $this->markTestSkipped('Passport personal access client is required.');
        }

        config([
            'api.login_user_ids' => [(int) $this->owner->id],
            'api.login_usernames' => [],
            'api.login_scopes' => ['user-read', 'accounts-orders', 'accounts-catalog', 'contacts-read', 'rewards'],
        ]);
        $this->owner->password = Hash::make('secret-pass');
        $this->owner->status = 'active';
        $this->owner->allow_login = 1;
        $this->owner->user_type = 'user';
        $this->owner->save();
        $this->owner->load('business');

        $response = $this->postJson('/api/login', [
            'username' => $this->owner->username,
            'password' => 'secret-pass',
        ])->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'expires_at']);

        $this->assertSame('Bearer', $response->json('token_type'));
        $this->assertNotEmpty($response->json('token'));

        $this->withHeader('Authorization', 'Bearer '.$response->json('token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', (int) $this->owner->id)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.service_staff_pin');
    }

    public function test_empty_allowlist_denies_all_logins(): void
    {
        config(['api.login_user_ids' => [], 'api.login_usernames' => []]);
        $this->owner->password = Hash::make('secret-pass');
        $this->owner->status = 'active';
        $this->owner->allow_login = 1;
        $this->owner->user_type = 'user';
        $this->owner->save();

        $this->postJson('/api/login', [
            'username' => $this->owner->username,
            'password' => 'secret-pass',
        ])->assertStatus(403);
    }
}
