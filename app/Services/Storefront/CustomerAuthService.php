<?php

namespace App\Services\Storefront;

use App\Contact;
use App\Utils\ContactUtil;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Customer registration and authentication for the storefront (Sanctum tokens on Contact).
 */
class CustomerAuthService
{
    public function __construct(
        private ContactUtil $contactUtil
    ) {
    }

    public function register(int $businessId, array $data): array
    {
        $email = $data['email'] ?? null;
        $mobile = $data['mobile'] ?? $data['phone'] ?? null;

        if ($email && Contact::where('business_id', $businessId)->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => ['Email already registered.']]);
        }

        if ($mobile && Contact::where('business_id', $businessId)->where('mobile', $mobile)->exists()) {
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
            'created_by' => 1,
        ];

        $result = $this->contactUtil->createNewContact($input);
        $contact = $result['data'] ?? Contact::find($result['id'] ?? null);
        if (! $contact instanceof Contact) {
            throw new \RuntimeException('Failed to create customer contact.');
        }
        $token = $contact->createToken('storefront')->plainTextToken;

        return [
            'contact' => $this->formatContact($contact),
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

    public function formatContact(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => $contact->email,
            'mobile' => $contact->mobile,
            'address_line_1' => $contact->address_line_1,
            'address_line_2' => $contact->address_line_2,
            'city' => $contact->city,
            'state' => $contact->state,
            'country' => $contact->country,
            'zip_code' => $contact->zip_code,
        ];
    }
}
