<?php

namespace App\Services\Storefront;

use App\Contact;

/**
 * Cash on delivery is open to everyone when `cod_enabled` is on; otherwise only
 * signed-in, verified customers listed in `cod_allowed_emails` may use it.
 */
class CodEligibility
{
    public static function isAvailable(array $settings, ?Contact $contact = null): bool
    {
        if (! empty($settings['cod_enabled'])) {
            return true;
        }

        return self::isAllowlisted($settings, $contact);
    }

    public static function isAllowlisted(array $settings, ?Contact $contact): bool
    {
        if (! $contact || ! $contact->isVerifiedForCheckout()) {
            return false;
        }

        $email = strtolower(trim((string) $contact->email));
        if ($email === '') {
            return false;
        }

        return in_array($email, self::normalizeEmails($settings['cod_allowed_emails'] ?? []), true);
    }

    /**
     * @return list<string> lowercase, unique, valid addresses
     */
    public static function normalizeEmails(mixed $emails): array
    {
        $clean = [];
        foreach ((array) $emails as $email) {
            if (! is_string($email)) {
                continue;
            }
            $email = strtolower(trim($email));
            if ($email !== '' && strlen($email) <= 191 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $clean[$email] = true;
            }
        }

        return array_keys($clean);
    }
}
