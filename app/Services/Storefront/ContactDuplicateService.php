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

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
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
        $needles = $this->phoneValidation->nationalDigitNeedles($mobile, $dialCode);
        if ($needles === []) {
            return null;
        }

        // Every stored format of the number ends with the same national digits; narrow in SQL before exact matching.
        $shortest = array_reduce($needles, fn ($carry, $n) => $carry === null || strlen($n) < strlen($carry) ? $n : $carry);
        $tail = substr((string) $shortest, -7);

        $contacts = Contact::where('business_id', $businessId)
            ->whereIn('type', ['customer', 'both'])
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(mobile, ' ', ''), '-', ''), '(', ''), ')', '') LIKE ?", ['%'.$tail])
            ->get(['id', 'mobile', 'email', 'type']);

        foreach ($contacts as $contact) {
            if ($this->phoneValidation->mobilesMatch((string) $contact->mobile, $mobile, $dialCode)) {
                return $contact;
            }
        }

        return null;
    }
}
