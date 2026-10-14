<?php

namespace App\Services\Storefront;

use App\Contact;
use App\Jobs\SyncCustomerToAccounts;
use App\Utils\ContactUtil;
use Illuminate\Support\Facades\Log;
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

        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $name = trim($firstName.' '.$lastName);

        $existing = $this->duplicates->findCustomerByMobile($businessId, $mobile, $dialCode);
        $created = ! $existing instanceof Contact;
        // The duplicate lookup selects only a few columns; reload the full row for syncing.
        $contact = $created
            ? $this->createContact($businessId, $data, $mobile, $email, $firstName, $lastName, $name)
            : (Contact::find($existing->id) ?? $existing);

        $this->queueAccountsSync($contact, $name, $email);

        return [
            // Public endpoint: never echo an existing customer's details back to an anonymous caller.
            'contact' => $created ? $this->formatContact($contact) : null,
            'message' => $created
                ? 'Customer account has been created successfully.'
                : 'This customer is already registered.',
            'created' => $created,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createContact(
        int $businessId,
        array $data,
        string $mobile,
        string $email,
        string $firstName,
        string $lastName,
        string $name
    ): Contact {
        if ($email !== '' && $this->duplicates->findCustomerByEmail($businessId, $email)) {
            throw ValidationException::withMessages(['email' => ['Email already registered.']]);
        }

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

        return $contact;
    }

    /**
     * Stored POS data wins over submitted data so an existing customer is not altered by the public form.
     */
    private function queueAccountsSync(Contact $contact, string $submittedName, string $submittedEmail): void
    {
        $email = trim((string) $contact->email) ?: $submittedEmail;
        $phone = trim((string) $contact->mobile);
        if ($email === '' || $phone === '') {
            return;
        }

        $name = trim((string) $contact->name) ?: ($submittedName !== '' ? $submittedName : $email);

        // On the sync queue driver a failing job throws here; the POS signup must still succeed.
        try {
            SyncCustomerToAccounts::dispatch($name, $email, $phone);
        } catch (\Throwable $e) {
            Log::warning('Accounts customer sync failed', ['contact_id' => $contact->id, 'error' => $e->getMessage()]);
        }
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
