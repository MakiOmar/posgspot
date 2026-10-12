<?php

namespace App\Services\Accounts;

use App\Product;
use App\Services\AccountsCatalogService;

/**
 * Save-time rules for synced Accounts products on POS sells and sales orders.
 *
 * The product row endpoint does not check not_for_selling, so these checks are what actually
 * keep Accounts offers on sales orders only (and offline slots off them).
 */
class SalesOrderAccountsGuard
{
    public function __construct(private SalesOrderDigitalFulfillmentService $fulfilment)
    {
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $products  request `products` rows
     * @param  callable(mixed): float  $parseQty  locale-aware number parser (TransactionUtil::num_uf)
     * @return string|null translated error, or null when the save may proceed
     */
    public function check(int $businessId, array $products, bool $isSalesOrder, ?int $salesOrderId, callable $parseQty): ?string
    {
        $productIds = array_values(array_unique(array_filter(array_map(
            fn ($row) => (int) ($row['product_id'] ?? 0),
            $products
        ))));
        $skus = $productIds === [] ? [] : Product::where('business_id', $businessId)
            ->whereIn('id', $productIds)
            ->pluck('sku', 'id')
            ->all();

        foreach ($products as $row) {
            $sku = $skus[(int) ($row['product_id'] ?? 0)] ?? null;
            if (! AccountsCatalogService::isAccountsSku($sku)) {
                continue;
            }
            $isNewLine = empty($row['transaction_sell_lines_id']) && empty($row['so_line_id']);
            if (! $isNewLine) {
                continue;
            }
            if (! $isSalesOrder) {
                return __('lang_v1.accounts_products_sales_order_only');
            }
            if (AccountsCatalogService::isOfflineSku($sku)) {
                return __('lang_v1.sales_order_offline_not_allowed');
            }
        }

        if ($isSalesOrder && $salesOrderId) {
            return $this->checkAllocatedLinesKept($products, $salesOrderId, $parseQty);
        }

        return null;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $products
     */
    private function checkAllocatedLinesKept(array $products, int $salesOrderId, callable $parseQty): ?string
    {
        $allocated = $this->fulfilment->allocatedUnitsBySellLine($salesOrderId);
        if ($allocated === []) {
            return null;
        }

        $submittedQty = [];
        foreach ($products as $row) {
            if (! empty($row['transaction_sell_lines_id'])) {
                $submittedQty[(int) $row['transaction_sell_lines_id']] = (float) $parseQty($row['quantity'] ?? 0);
            }
        }

        foreach ($allocated as $sellLineId => $units) {
            if (! isset($submittedQty[$sellLineId]) || $submittedQty[$sellLineId] < $units) {
                return __('lang_v1.allocated_account_line_locked');
            }
        }

        return null;
    }
}
