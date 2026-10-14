<?php

namespace App\Services\Storefront\Accounts;

use Illuminate\Support\Facades\Log;

/**
 * Find-or-create a customer in Accounts without ever creating a second user for the same phone or email.
 */
class AccountsCustomerSync
{
    public const SYNCED = 'synced';

    /** Accounts already has this email under a different phone. */
    public const EMAIL_TAKEN = 'email_taken';

    /** Accounts unreachable or misconfigured; safe to retry later. */
    public const UNAVAILABLE = 'unavailable';

    /** Accounts rejected the payload for another reason; retrying will not help. */
    public const REJECTED = 'rejected';

    public function __construct(private AccountsApiClient $accounts)
    {
    }

    public function isConfigured(): bool
    {
        return $this->accounts->baseUrl() !== '';
    }

    public function ensure(string $name, string $email, string $phone): string
    {
        $result = $this->accounts->createCustomer($name, $email, $phone);
        if ($result['success']) {
            return self::SYNCED;
        }

        if ($result['status'] === 422) {
            $emailTaken = ! empty($result['body']['errors']['email']);
            Log::warning('Accounts customer sync rejected', [
                'phone' => $phone,
                'email' => $email,
                'error' => $result['error'],
            ]);

            return $emailTaken ? self::EMAIL_TAKEN : self::REJECTED;
        }

        Log::warning('Accounts customer sync unavailable', ['status' => $result['status'], 'error' => $result['error']]);

        return self::UNAVAILABLE;
    }
}
