<?php

namespace App\Services\Storefront;

/**
 * Platform-strict digital game offer fields.
 *
 * List/PDP must not fall back to the other PlayStation platform or aggregate
 * totals — that shows "in stock" for an offer Accounts will refuse at check-stock.
 */
final class DigitalGameOffer
{
    /** @var list<string> */
    public const TYPES = ['primary', 'secondary', 'full'];

    /**
     * @param  array<string, mixed>  $game
     */
    public static function stock(array $game, string $platform, string $type): int
    {
        return self::intField($game, 'ps'.$platform.'_'.$type.'_stock');
    }

    /**
     * @param  array<string, mixed>  $game
     */
    public static function enabled(array $game, string $platform, string $type): bool
    {
        $statusKey = 'ps'.$platform.'_'.$type.'_status';
        if (array_key_exists($statusKey, $game) && $game[$statusKey] !== null && $game[$statusKey] !== '') {
            return self::boolField($game, $statusKey);
        }

        // Detail payloads often omit *_full_status — treat sellable stock as enabled.
        if ($type === 'full') {
            return self::stock($game, $platform, 'full') > 0
                || self::price($game, $platform, 'full') > 0;
        }

        return self::boolField($game, $statusKey);
    }

    /**
     * Price may use generic keys when the platform-specific price is missing.
     * Stock and status stay platform-strict.
     *
     * @param  array<string, mixed>  $game
     */
    public static function price(array $game, string $platform, string $type): float
    {
        $specific = self::numericOrNull($game, 'ps'.$platform.'_'.$type.'_price');
        if ($specific !== null && $specific > 0) {
            return $specific;
        }
        $generic = match ($type) {
            'secondary' => 'secondary_price',
            'full' => 'full_price',
            default => 'primary_price',
        };
        $fallback = self::numericOrNull($game, $generic);

        return $fallback !== null && $fallback > 0 ? $fallback : 0.0;
    }

    /**
     * @param  array<string, mixed>  $typeRow
     */
    public static function listOfferInStock(array $typeRow): bool
    {
        $stock = self::intish($typeRow['stock'] ?? 0);

        return ! empty($typeRow['available']) && $stock > 0;
    }

    /**
     * Accounts check_stock payload: trust is_available; only use stock when present.
     *
     * @param  array<string, mixed>  $body
     */
    public static function checkStockIndicatesAvailable(array $body): bool
    {
        if (isset($body['data']) && is_array($body['data'])) {
            $body = array_merge($body, $body['data']);
        }

        if (array_key_exists('is_available', $body)) {
            $flag = $body['is_available'];
            if ($flag === false || $flag === 0 || $flag === '0' || $flag === 'false') {
                return false;
            }
            if ($flag === true || $flag === 1 || $flag === '1' || $flag === 'true') {
                return true;
            }
        }

        if (! array_key_exists('stock', $body) || $body['stock'] === null || $body['stock'] === '') {
            return false;
        }

        return (float) $body['stock'] > 0;
    }

    /**
     * Cast platform stock fields to ints so JSON keeps explicit zeros.
     *
     * @param  array<string, mixed>  $game
     * @return array<string, mixed>
     */
    public static function normalizeDetail(array $game): array
    {
        foreach (['4', '5'] as $platform) {
            foreach (self::TYPES as $type) {
                $stockKey = 'ps'.$platform.'_'.$type.'_stock';
                $game[$stockKey] = self::intField($game, $stockKey);
            }
        }

        return $game;
    }

    public static function normalizeType(string $type): string
    {
        return in_array($type, self::TYPES, true) ? $type : 'primary';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function intField(array $row, string $key): int
    {
        if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
            return 0;
        }

        return self::intish($row[$key]);
    }

    public static function intish(mixed $value): int
    {
        if ($value === null || $value === '' || $value === false) {
            return 0;
        }

        return max(0, (int) round((float) $value));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function boolField(array $row, string $key): bool
    {
        if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
            return false;
        }
        $value = $row[$key];

        return $value === true || $value === 1 || $value === '1';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function numericOrNull(array $row, string $key): ?float
    {
        if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
            return null;
        }
        if (! is_numeric($row[$key])) {
            return null;
        }

        return (float) $row[$key];
    }
}
