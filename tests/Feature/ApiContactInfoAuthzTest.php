<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ApiContactInfoAuthzTest extends TestCase
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

    public function test_contact_info_omits_business_secrets(): void
    {
        Passport::actingAs($this->owner, ['contacts-read'], 'api');

        $contact = Contact::where('business_id', 1)->whereIn('type', ['customer', 'both'])->orderBy('id')->first();
        if (! $contact) {
            $this->markTestSkipped('No customer contact on business 1.');
        }

        $response = $this->getJson('/api/contacts/'.$contact->id.'/info')->assertOk();
        $data = $response->json('data');

        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('email_settings', $data);
        $this->assertArrayNotHasKey('sms_settings', $data);
        $this->assertArrayNotHasKey('woocommerce_wh_oc_secret', $data);
        $this->assertArrayNotHasKey('email_verify_code_hash', $data);
        $this->assertArrayNotHasKey('business', $data);
        $this->assertSame((int) $contact->id, (int) $data['id']);
    }

    public function test_contact_info_requires_contacts_read_scope(): void
    {
        Passport::actingAs($this->owner, ['user-read'], 'api');

        $this->getJson('/api/contacts/1/info')->assertStatus(403);
    }
}
