<?php

namespace App\Services\Storefront\Shipping;

/**
 * Opaque signed shipping rate ids: method_id + amount (tamper-resistant).
 */
class ShippingRateId
{
    public static function encode(int $methodId, float $amount): string
    {
        $payload = $methodId.'|'.number_format($amount, 4, '.', '');
        $sig = hash_hmac('sha256', $payload, (string) config('app.key'));

        return rtrim(strtr(base64_encode($payload.'|'.substr($sig, 0, 16)), '+/', '-_'), '=');
    }

    /**
     * @return array{method_id:int,amount:float}|null
     */
    public static function decode(string $id): ?array
    {
        $raw = base64_decode(strtr($id, '-_', '+/'), true);
        if ($raw === false) {
            return null;
        }

        $parts = explode('|', $raw);
        if (count($parts) !== 3) {
            return null;
        }

        [$methodId, $amount, $sig] = $parts;
        $payload = $methodId.'|'.$amount;
        $expected = substr(hash_hmac('sha256', $payload, (string) config('app.key')), 0, 16);
        if (! hash_equals($expected, $sig)) {
            return null;
        }

        return [
            'method_id' => (int) $methodId,
            'amount' => (float) $amount,
        ];
    }
}
