<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Supported storefront content locales (public API + Qwik UI).
 */
class StorefrontLocale
{
    public const DEFAULT = 'en';

    /** @var list<string> */
    public const SUPPORTED = ['en', 'ar'];

    public static function resolve(Request $request): string
    {
        $raw = $request->header('X-Content-Locale');
        if (empty($raw)) {
            $raw = $request->header('Accept-Language', '');
            $raw = explode(',', (string) $raw)[0] ?? '';
            $raw = trim(explode(';', $raw)[0]);
        }

        $locale = strtolower(substr(trim((string) $raw), 0, 2));

        return in_array($locale, self::SUPPORTED, true) ? $locale : self::DEFAULT;
    }

    public static function isDefault(string $locale): bool
    {
        return $locale === self::DEFAULT;
    }

    public static function fromRequest(Request $request): string
    {
        $locale = $request->attributes->get('storefront_content_locale');

        return is_string($locale) && in_array($locale, self::SUPPORTED, true)
            ? $locale
            : self::resolve($request);
    }
}
