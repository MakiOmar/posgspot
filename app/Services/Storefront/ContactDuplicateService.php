<?php

namespace App\Services\Storefront;

use App\Contact;

/**
 * Detect duplicate storefront customers by email or mobile (including local formats without dial code).
 */
class ContactDuplicateService
{
    public function __construct(private PhoneValidationService $phoneValidation)
    {
    }

    public function findCustomerByEmail(int $businessId, string $email): ?Contact
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return null;
        }

        return Contact::where('business_id', $businessId)
            ->whereIn('type', ['customer', 'both'])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->first();
    }

    public function findCustomerByMobile(int $businessId, string $mobile, ?string $dialCode = null): ?Contact
    {
        $needle = $this->phoneValidation->canonicalNationalDigits($mobile, $dialCode);
        if ($needle === '') {
            return null;
        }

        $contacts = Contact::where('business_id', $businessId)
            ->whereIn('type', ['customer', 'both'])
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->get(['id', 'mobile', 'email', 'type']);

        foreach ($contacts as $contact) {
            if ($this->phoneValidation->canonicalNationalDigits($contact->mobile) === $needle) {
                return $contact;
            }
        }

        return null;
    }
}
