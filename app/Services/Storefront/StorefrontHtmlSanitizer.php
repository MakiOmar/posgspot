<?php

namespace App\Services\Storefront;

/**
 * Strip dangerous markup from public catalog HTML (product descriptions).
 * Defense-in-depth alongside storefront CSP + client DOMPurify.
 */
class StorefrontHtmlSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><a><span><div><blockquote>';

    public function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $trimmed = trim($html);
        if ($trimmed === '') {
            return '';
        }

        $clean = strip_tags($html, self::ALLOWED_TAGS);
        $clean = preg_replace('/\s*on\w+\s*=\s*("([^"]*)"|\'([^\']*)\'|[^\s>]+)/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/\s(href|src|xlink:href)\s*=\s*("\s*javascript:[^"]*"|\'\s*javascript:[^\']*\'|javascript:[^\s>]+)/iu', '', $clean) ?? $clean;

        return $clean;
    }
}
