<?php

namespace App\Support;

/**
 * Redacts personal data returned by lookups that are keyed on guessable values (phone, job number).
 */
final class PiiMask
{
    /** Keep only the last characters, e.g. "SN12345678" -> "******5678". */
    public static function tail(?string $value, int $visible = 4): string
    {
        $value = trim((string) $value);
        $length = mb_strlen($value);
        if ($length === 0) {
            return '';
        }
        if ($length <= $visible) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - $visible).mb_substr($value, -$visible);
    }

    /** First name plus last initial, e.g. "Ahmed Mohamed Ali" -> "Ahmed A.". */
    public static function name(?string $value): string
    {
        $parts = preg_split('/\s+/u', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return '';
        }
        $first = array_shift($parts);
        if ($parts === []) {
            return $first;
        }

        return $first.' '.mb_substr((string) end($parts), 0, 1).'.';
    }
}
