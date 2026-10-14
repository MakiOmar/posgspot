<?php

namespace App\Services\Storefront;

use App\Contact;
use App\Jobs\SyncCustomerToAccounts;
use App\Services\Storefront\Accounts\AccountsCustomerSync;
use App\Utils\ContactUtil;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Public storefront customer registration (add-customer landing page).
 *
 * One phone and one email map to a single customer in both POS and Accounts:
 * POS duplicates are checked first, then Accounts is asked to find-or-create
 * before the POS contact is written.
 */
class AddCustomerService
{
    public function __construct(
        private ContactUtil $contactUtil,
        private PhoneValidationService $phoneValidation,
        private ContactDuplicateService $duplicates,
        private AccountsCustomerSync $accountsSync
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{contact: array<string, mixed>|null, message: string, created: bool}
     */
    public function register(int $businessId, array $data): array
    {
        $mobile = (string) ($data['mobile'] ?? '');
        $dialCode = $data['dial_code'] ?? $this->phoneValidation->inferDialCodeFromMobile($mobile);

        $phoneCheck = $this->phoneValidation->validate($mobile, $dialCode);
        if (! $phoneCheck['valid']) {
            throw ValidationException::withMessages(['mobile' => [$phoneCheck['message']]]);
        }

        // Serialize signups for the same number so concurrent submits cannot both pass the duplicate check.
        $lockKey = 'add-customer:'.$businessId.':'.$this->phoneValidation->canonicalNationalDigits($mobile, $dialCode);
        try {
            return Cache::lock($lockKey, 30)->block(15, fn () => $this->registerLocked($businessId, $data, $mobile, $dialCode));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['mobile' => ['This number is being registered, please try again.']]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{contact: array<string, mixed>|null, message: string, created: bool}
     */
    private function registerLocked(int $businessId, array $data, string $mobile, string $dialCode): array
    {
        $email = ContactDuplicateService::normalizeEmail((string) ($data['email'] ?? ''));
        $name = trim(trim((string) ($data['first_name'] ?? '')).' '.trim((string) ($data['last_name'] ?? '')));

        $existing = $this->duplicates->findCustomerByMobile($businessId, $mobile, $dialCode);
        if ($existing instanceof Contact) {
            // The duplicate lookup selects only a few columns; reload the full row for syncing.
            $this->syncExistingContact(Contact::find($existing->id) ?? $existing, $businessId, $email);

            return [
                // Public endpoint: never echo an existing customer's details back to an anonymous caller.
                'contact' => null,
                'message' => 'This customer is already registered.',
                'created' => false,
            ];
        }

        if ($email !== '' && $this->duplicates->findCustomerByEmail($businessId, $email)) {
            throw ValidationException::withMessages(['email' => ['Email already registered.']]);
        }

        $this->ensureInAccounts($name !== '' ? $name : $email, $email, $mobile, true);
        $contact = $this->createContact($businessId, $data, $mobile, $email, $name);

        return [
            'contact' => $this->formatContact($contact),
            'message' => 'Customer account has been created successfully.',
            'created' => true,
        ];
    }

    /**
     * Stored POS data wins over submitted data so an existing customer is not altered by the public form.
     */
    private function syncExistingContact(Contact $contact, int $businessId, string $submittedEmail): void
    {
        $email = ContactDuplicateService::normalizeEmail((string) $contact->email);
        if ($email === '' && $submittedEmail !== '') {
            $owner = $this->duplicates->findCustomerByEmail($businessId, $submittedEmail);
            $email = ! $owner || $owner->id === $contact->id ? $submittedEmail : '';
        }

        $phone = trim((string) $contact->mobile);
        if ($email === '' || $phone === '') {
            return;
        }

        $this->ensureInAccounts(trim((string) $contact->name) ?: $email, $email, $phone, false);
    }

    /**
     * @param  bool  $rejectTakenEmail  New signups fail when Accounts has the email under another phone.
     */
    private function ensureInAccounts(string $name, string $email, string $phone, bool $rejectTakenEmail): void
    {
        if ($email === '' || ! $this->accountsSync->isConfigured()) {
            return;
        }

        $status = $this->accountsSync->ensure($name, $email, $phone);
        if ($status === AccountsCustomerSync::EMAIL_TAKEN && $rejectTakenEmail) {
            throw ValidationException::withMessages(['email' => ['Email already registered.']]);
        }

        if ($status === AccountsCustomerSync::UNAVAILABLE) {
            $this->queueRetry($name, $email, $phone);
        }
    }

    private function queueRetry(string $name, string $email, string $phone): void
    {
        // On the sync queue driver a failing job throws here; the POS signup must still succeed.
        try {
            SyncCustomerToAccounts::dispatch($name, $email, $phone);
        } catch (\Throwable $e) {
            Log::warning('Accounts customer sync retry failed', ['phone' => $phone, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createContact(int $businessId, array $data, string $mobile, string $email, string $name): Contact
    {
        $result = $this->contactUtil->createNewContact([
            'business_id' => $businessId,
            'type' => 'customer',
            'contact_status' => 'active',
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => trim((string) ($data['last_name'] ?? '')),
            'name' => $name !== '' ? $name : ($email !== '' ? $email : 'Customer'),
            'email' => $email !== '' ? $email : null,
            'mobile' => $mobile,
            'country' => $data['country'] ?? null,
            'state' => $data['state'] ?? null,
            'dob' => $data['birth_date'] ?? null,
            'created_by' => 1,
        ]);
        $contact = $result['data'] ?? Contact::find($result['id'] ?? null);

        if (! $contact instanceof Contact) {
            throw new \RuntimeException('Failed to create customer contact.');
        }

        return $contact;
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
