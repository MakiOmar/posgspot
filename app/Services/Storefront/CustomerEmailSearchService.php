<?php

namespace App\Services\Storefront;

use App\Contact;

/**
 * Admin lookup of business customers that have an email (COD allowlist picker).
 */
class CustomerEmailSearchService
{
    public const LIMIT = 20;

    /**
     * @return list<array{id: string, text: string}> select2 results keyed by lowercase email
     */
    public function search(int $businessId, string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return Contact::query()
            ->where('business_id', $businessId)
            ->whereIn('type', ['customer', 'both'])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where(function ($query) use ($like) {
                $query->where('email', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('mobile', 'like', $like);
            })
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'email', 'email_verified_at'])
            ->unique(fn (Contact $contact) => strtolower(trim((string) $contact->email)))
            ->map(fn (Contact $contact) => self::option($contact))
            ->values()
            ->all();
    }

    /**
     * @return array{id: string, text: string}
     */
    private static function option(Contact $contact): array
    {
        $email = strtolower(trim((string) $contact->email));
        $name = trim((string) $contact->name);
        $label = $name !== '' ? $name.' — '.$email : $email;
        if (empty($contact->email_verified_at)) {
            $label .= ' ('.__('lang_v1.storefront_cod_email_unverified').')';
        }

        return ['id' => $email, 'text' => $label];
    }
}
