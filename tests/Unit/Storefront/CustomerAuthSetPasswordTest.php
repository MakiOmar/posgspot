<?php

namespace Tests\Unit\Storefront;

use App\Contact;
use App\Services\Storefront\CustomerAuthService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * POS / service-level password setter for storefront contacts.
 */
class CustomerAuthSetPasswordTest extends TestCase
{
    protected int $businessId = 1;

    public function test_set_password_hashes_and_revokes_tokens(): void
    {
        $contact = new Contact();
        $contact->business_id = $this->businessId;
        $contact->type = 'customer';
        $contact->name = 'Pwd Test '.uniqid();
        $contact->first_name = 'Pwd';
        $contact->email = 'pos_pwd_'.uniqid().'@example.com';
        $contact->mobile = '100'.random_int(1000000, 9999999);
        $contact->created_by = 1;
        $contact->password = Hash::make('oldpassword123');
        $contact->save();

        $contact->createToken('storefront');
        $this->assertSame(1, $contact->tokens()->count());

        app(CustomerAuthService::class)->setPassword($contact, 'newpassword123', true);

        $fresh = $contact->fresh();
        $this->assertTrue(Hash::check('newpassword123', $fresh->password));
        $this->assertFalse(Hash::check('oldpassword123', $fresh->password));
        $this->assertSame(0, $fresh->tokens()->count());
    }

    public function test_set_password_can_keep_tokens(): void
    {
        $contact = new Contact();
        $contact->business_id = $this->businessId;
        $contact->type = 'customer';
        $contact->name = 'Pwd Keep '.uniqid();
        $contact->first_name = 'Keep';
        $contact->email = 'pos_keep_'.uniqid().'@example.com';
        $contact->mobile = '100'.random_int(1000000, 9999999);
        $contact->created_by = 1;
        $contact->password = Hash::make('oldpassword123');
        $contact->save();

        $contact->createToken('storefront');

        app(CustomerAuthService::class)->setPassword($contact, 'anotherpassword', false);

        $this->assertTrue(Hash::check('anotherpassword', $contact->fresh()->password));
        $this->assertSame(1, $contact->tokens()->count());
    }
}
