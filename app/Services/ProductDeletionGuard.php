<?php

namespace App\Services;

use App\TransactionSellLine;

/**
 * Sell lines cascade-delete with their product (FK ON DELETE CASCADE), so a sold
 * product must never be hard-deleted — it would strip lines from past invoices.
 * Staff should deactivate such products instead.
 */
class ProductDeletionGuard
{
    /**
     * Product IDs (from the given list) that appear on any sell line of the business.
     *
     * @param  array<int, int|string>  $productIds
     * @return array<int, int>
     */
    public function productIdsWithSales(int $businessId, array $productIds): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            return [];
        }

        return TransactionSellLine::query()
            ->join('transactions as T', 'transaction_sell_lines.transaction_id', '=', 'T.id')
            ->where('T.business_id', $businessId)
            ->whereIn('transaction_sell_lines.product_id', $productIds)
            ->distinct()
            ->pluck('transaction_sell_lines.product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function hasSales(int $businessId, int $productId): bool
    {
        return $this->productIdsWithSales($businessId, [$productId]) !== [];
    }
}
