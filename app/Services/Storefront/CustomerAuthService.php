<?php

namespace App\Services\Storefront;

use App\Contact;
use App\Mail\StorefrontEmailVerification;
use App\Utils\ContactUtil;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Customer registration and authentication for the storefront (Sanctum tokens on Contact).
 */
class CustomerAuthService
{
    public const VERIFY_CODE_TTL_MINUTES = 30;

    public function __construct(
        private ContactUtil $contactUtil,
        private ContactDuplicateService $duplicates
    ) {
    }

    public function register(int $businessId, array $data): array
    {
        $email = isset($data['email']) ? trim((string) $data['email']) : null;
        $mobile = isset($data['mobile']) ? trim((string) $data['mobile']) : null;
        if ($mobile === '') {
            $mobile = null;
        }
        $dialCode = $data['dial_code'] ?? null;

        if ($email && $this->duplicates->findCustomerByEmail($businessId, $email)) {
            throw ValidationException::withMessages(['email' => ['Email already registered.']]);
        }

        if ($mobile && $this->duplicates->findCustomerByMobile($businessId, (string) $mobile, $dialCode)) {
            throw ValidationException::withMessages(['mobile' => ['Mobile already registered.']]);
        }

        $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
        if ($name === '') {
            $name = $data['name'] ?? 'Customer';
        }

        $input = [
            'business_id' => $businessId,
            'type' => 'customer',
            'contact_status' => 'active',
            'name' => $name,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => $email,
            'mobile' => $mobile ?? '',
            'password' => Hash::make($data['password']),
            'email_verified_at' => null,
            'created_by' => 1,
        ];

        $result = $this->contactUtil->createNewContact($input);
        $contact = $result['data'] ?? Contact::find($result['id'] ?? null);
        if (! $contact instanceof Contact) {
            throw new \RuntimeException('Failed to create customer contact.');
        }

        $this->issueEmailVerificationCode($contact);

        $token = $contact->createToken('storefront')->plainTextToken;

        return [
            'contact' => $this->formatContact($contact->fresh()),
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    public function login(int $businessId, string $login, string $password): array
    {
        $contact = Contact::where('business_id', $businessId)
            ->whereIn('type', ['customer', 'both'])
            ->where(function ($q) use ($login) {
                $q->where('email', $login)->orWhere('mobile', $login);
            })
            ->first();

        if (empty($contact) || empty($contact->password) || ! Hash::check($password, $contact->password)) {
            throw ValidationException::withMessages(['login' => ['Invalid credentials.']]);
        }

        $contact->tokens()->delete();
        $token = $contact->createToken('storefront')->plainTextToken;

        return [
            'contact' => $this->formatContact($contact),
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Generate a 6-digit OTP, persist hash + expiry, and email it.
     */
    public function issueEmailVerificationCode(Contact $contact, bool $force = false): void
    {
        if (! empty($contact->email_verified_at) && ! $force) {
            return;
        }

        if (empty($contact->email)) {
            throw ValidationException::withMessages(['email' => ['Email is required for verification.']]);
        }

        $code = (string) random_int(100000, 999999);
        $contact->email_verify_code_hash = Hash::make($code);
        $contact->email_verify_expires_at = now()->addMinutes(self::VERIFY_CODE_TTL_MINUTES);
        if ($force) {
            $contact->email_verified_at = null;
        }
        $contact->save();

        try {
            Mail::to($contact->email)->queue(new StorefrontEmailVerification($contact, $code));
        } catch (\Throwable $e) {
            Log::warning('Storefront email verification send failed.', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }
    }

    /**
     * Verify OTP for a contact (by auth user or email lookup).
     */
    public function verifyEmailCode(int $businessId, string $code, ?Contact $authContact = null, ?string $email = null): Contact
    {
        $contact = $authContact;
        if (! $contact) {
            if (empty($email)) {
                throw ValidationException::withMessages(['email' => ['Email is required.']]);
            }
            $contact = Contact::where('business_id', $businessId)
                ->whereIn('type', ['customer', 'both'])
                ->where('email', trim($email))
                ->first();
        }

        if (! $contact) {
            throw ValidationException::withMessages(['code' => ['Invalid or expired verification code.']]);
        }

        if (! empty($contact->email_verified_at)) {
            return $contact;
        }

        if (
            empty($contact->email_verify_code_hash)
            || empty($contact->email_verify_expires_at)
            || now()->gt($contact->email_verify_expires_at)
            || ! Hash::check($code, $contact->email_verify_code_hash)
        ) {
            throw ValidationException::withMessages(['code' => ['Invalid or expired verification code.']]);
        }

        $contact->email_verified_at = now();
        $contact->email_verify_code_hash = null;
        $contact->email_verify_expires_at = null;
        $contact->save();

        return $contact;
    }

    public function changePasswordAndIssueToken(Contact $contact, string $currentPassword, string $newPassword): string
    {
        if (empty($contact->password) || ! Hash::check($currentPassword, $contact->password)) {
            throw ValidationException::withMessages(['current_password' => ['Current password is incorrect.']]);
        }

        $contact->password = Hash::make($newPassword);
        $contact->save();
        $contact->tokens()->delete();

        return $contact->createToken('storefront')->plainTextToken;
    }

    public function formatContact(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => $contact->email,
            'mobile' => $contact->mobile,
            'email_verified' => ! empty($contact->email_verified_at),
            'delete_requested' => ! empty($contact->storefront_delete_requested_at),
            'address_line_1' => $contact->address_line_1,
            'address_line_2' => $contact->address_line_2,
            'city' => $contact->city,
            'state' => $contact->state,
            'country' => $contact->country,
            'zip_code' => $contact->zip_code,
        ];
    }
}
