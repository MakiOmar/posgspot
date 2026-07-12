<?php

namespace App\Services\Storefront;

use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Temporary diagnostics for digital checkout lines saving / rendering as L.E. 0.00.
 * Enable with APP_DEBUG=true, STOREFRONT_PRICE_DEBUG=true, or ?sf_debug=1 / header X-Storefront-Debug: 1.
 */
class StorefrontPriceDebug
{
    public static function enabled(?\Illuminate\Http\Request $request = null): bool
    {
        if (filter_var(config('storefront.price_debug', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        if (config('app.debug')) {
            return true;
        }
        $request = $request ?? request();
        if ($request && (
            $request->boolean('sf_debug')
            || $request->header('X-Storefront-Debug') === '1'
        )) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(string $stage, array $context = []): void
    {
        Log::warning('storefront.digital.price.'.$stage, $context);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    public static function snapshotCheckoutInput(array $items, array $products, float $subtotal, float $finalTotal): array
    {
        $itemSnap = [];
        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $digital = is_array($item['digital'] ?? null) ? $item['digital'] : null;
            $itemSnap[] = [
                'index' => $i,
                'variation_id' => $item['variation_id'] ?? null,
                'quantity' => $item['quantity'] ?? null,
                'unit_price' => $item['unit_price'] ?? null,
                'price' => $item['price'] ?? null,
                'digital_kind' => $digital['kind'] ?? null,
                'digital_price' => $digital['price'] ?? null,
                'digital_price_type' => isset($digital['price']) ? gettype($digital['price']) : null,
                'digital_title' => $digital['title'] ?? null,
                'digital_game_id' => $digital['game_id'] ?? null,
                'digital_type' => $digital['type'] ?? null,
                'digital_platform' => $digital['platform'] ?? null,
            ];
        }

        $productSnap = [];
        foreach ($products as $i => $product) {
            if (! is_array($product)) {
                continue;
            }
            $productSnap[] = [
                'index' => $i,
                'product_id' => $product['product_id'] ?? null,
                'variation_id' => $product['variation_id'] ?? null,
                'quantity' => $product['quantity'] ?? null,
                'unit_price' => $product['unit_price'] ?? null,
                'unit_price_type' => isset($product['unit_price']) ? gettype($product['unit_price']) : null,
                'unit_price_inc_tax' => $product['unit_price_inc_tax'] ?? null,
                'item_tax' => $product['item_tax'] ?? null,
                'tax_id' => $product['tax_id'] ?? null,
                'sell_line_note' => $product['sell_line_note'] ?? null,
            ];
        }

        return [
            'subtotal' => $subtotal,
            'final_total' => $finalTotal,
            'items' => $itemSnap,
            'products_payload' => $productSnap,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshotTransaction(Transaction $transaction): array
    {
        $lines = DB::table('transaction_sell_lines')
            ->where('transaction_id', $transaction->id)
            ->orderBy('id')
            ->get([
                'id',
                'product_id',
                'variation_id',
                'quantity',
                'unit_price_before_discount',
                'unit_price',
                'unit_price_inc_tax',
                'item_tax',
                'tax_id',
                'line_discount_amount',
                'parent_sell_line_id',
                'sell_line_note',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();

        $tx = DB::table('transactions')
            ->where('id', $transaction->id)
            ->first([
                'id',
                'invoice_no',
                'source',
                'storefront_order_id',
                'total_before_tax',
                'tax_amount',
                'discount_amount',
                'shipping_charges',
                'final_total',
                'payment_status',
            ]);

        return [
            'transaction' => $tx ? (array) $tx : null,
            'sell_lines_db' => $lines,
            'any_line_zero' => collect($lines)->contains(function ($line) {
                return empty($line['parent_sell_line_id'])
                    && (float) ($line['unit_price_inc_tax'] ?? 0) <= 0;
            }),
        ];
    }

    /**
     * Compact payload for sell-details UI / API.
     *
     * @return array<string, mixed>
     */
    public static function viewPanelData(Transaction $sell): array
    {
        $snap = self::snapshotTransaction($sell);
        $eloquentLines = [];
        foreach ($sell->sell_lines as $line) {
            $eloquentLines[] = [
                'id' => $line->id,
                'variation_id' => $line->variation_id,
                'quantity' => $line->quantity,
                'unit_price_before_discount' => $line->unit_price_before_discount,
                'unit_price' => $line->unit_price,
                'unit_price_inc_tax' => $line->unit_price_inc_tax,
                'item_tax' => $line->item_tax,
                'sub_unit_id' => $line->sub_unit_id,
                'sell_line_note' => $line->sell_line_note,
                'computed_subtotal' => (float) $line->quantity * (float) $line->unit_price_inc_tax,
            ];
        }

        return array_merge($snap, [
            'eloquent_sell_lines' => $eloquentLines,
            'view_will_show_zero' => collect($eloquentLines)->contains(
                fn ($line) => (float) ($line['unit_price_inc_tax'] ?? 0) <= 0
            ),
        ]);
    }
}
