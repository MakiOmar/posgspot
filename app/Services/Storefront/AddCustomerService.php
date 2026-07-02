<?php

namespace App\Services\Storefront;

use App\Contact;
use App\Utils\ContactUtil;
use Illuminate\Validation\ValidationException;

/**
 * Public storefront customer registration (add-customer landing page).
 */
class AddCustomerService
{
    public function __construct(
        private ContactUtil $contactUtil,
        private PhoneValidationService $phoneValidation,
        private ContactDuplicateService $duplicates
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{contact: array<string, mixed>, message: string, created: bool}
     */
    public function register(int $businessId, array $data): array
    {
        $mobile = (string) ($data['mobile'] ?? '');
        $dialCode = $data['dial_code'] ?? $this->phoneValidation->inferDialCodeFromMobile($mobile);
        $email = isset($data['email']) ? trim((string) $data['email']) : '';

        $phoneCheck = $this->phoneValidation->validate($mobile, $dialCode);
        if (! $phoneCheck['valid']) {
            throw ValidationException::withMessages(['mobile' => [$phoneCheck['message']]]);
        }

        if ($email !== '' && $this->duplicates->findCustomerByEmail($businessId, $email)) {
            throw ValidationException::withMessages(['email' => ['Email already registered.']]);
        }

        if ($this->duplicates->findCustomerByMobile($businessId, $mobile, $dialCode)) {
            throw ValidationException::withMessages(['mobile' => ['Mobile already registered.']]);
        }

        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $name = trim($firstName.' '.$lastName);

        $input = [
            'business_id' => $businessId,
            'type' => 'customer',
            'contact_status' => 'active',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => $name !== '' ? $name : ($email !== '' ? $email : 'Customer'),
            'email' => $email !== '' ? $email : null,
            'mobile' => $mobile,
            'country' => $data['country'] ?? null,
            'state' => $data['state'] ?? null,
            'dob' => $data['birth_date'] ?? null,
            'created_by' => 1,
        ];

        $result = $this->contactUtil->createNewContact($input);
        $contact = $result['data'] ?? Contact::find($result['id'] ?? null);

        if (! $contact instanceof Contact) {
            throw new \RuntimeException('Failed to create customer contact.');
        }

        return [
            'contact' => $this->formatContact($contact),
            'message' => 'Customer account has been created successfully.',
            'created' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatContact(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => $contact->email,
            'mobile' => $contact->mobile,
            'country' => $contact->country,
            'state' => $contact->state,
            'dob' => $contact->dob,
        ];
    }
}
