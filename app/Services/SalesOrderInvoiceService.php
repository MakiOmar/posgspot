<?php

namespace App\Services;

use App\Business;
use App\AccountTransaction;
use App\Product;
use App\Transaction;
use App\TransactionPayment;
use App\Variation;
use App\VariationLocationDetails;
use App\Utils\BusinessUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Converts a fully paid sales order into a final sell (invoice + stock).
 * Payments are MOVED (not copied) so cashbook is not double-posted.
 */
class SalesOrderInvoiceService
{
    public function __construct(
        private TransactionUtil $transactionUtil,
        private ProductUtil $productUtil,
        private BusinessUtil $businessUtil,
    ) {
    }

    /**
     * @return array{status: string, sell?: Transaction|null, msg?: string, reason?: string}
     */
    public function convertIfPaid(Transaction $so, ?int $userId = null): array
    {
        $so->loadMissing(['sell_lines.product', 'payment_lines']);

        if ($so->type !== 'sales_order') {
            return ['status' => 'skipped', 'reason' => 'not_sales_order', 'msg' => __('messages.something_went_wrong')];
        }

        $business = Business::find($so->business_id);
        if (empty($business)) {
            return ['status' => 'skipped', 'reason' => 'no_business', 'msg' => __('messages.something_went_wrong')];
        }

        $posSettings = empty($business->pos_settings)
            ? $this->businessUtil->defaultPosSettings()
            : (json_decode($business->pos_settings, true) ?: []);

        if (! $this->businessUtil->isSalesOrderInvoiceOnFullPaymentEnabled($posSettings)) {
            return ['status' => 'skipped', 'reason' => 'setting_off', 'msg' => ''];
        }

        // Ensure payment_status is current (completed lines only).
        $paymentStatus = $this->transactionUtil->updatePaymentStatus($so->id, $so->final_total);
        $so->payment_status = $paymentStatus;

        if ($paymentStatus !== 'paid') {
            return [
                'status' => 'skipped',
                'reason' => 'not_paid',
                'msg' => __('lang_v1.so_invoice_not_paid'),
            ];
        }

        $products = $this->buildProductsPayload($so);
        if ($products === []) {
            return [
                'status' => 'skipped',
                'reason' => 'already_invoiced',
                'msg' => __('lang_v1.so_invoice_already_invoiced'),
            ];
        }

        $allowOverselling = ! empty($posSettings['allow_overselling']);
        if (! $allowOverselling && ! $this->hasSufficientStock($so->location_id, $products)) {
            return [
                'status' => 'skipped',
                'reason' => 'insufficient_stock',
                'msg' => __('lang_v1.so_invoice_stock_insufficient'),
            ];
        }

        $userId = $userId ?: (int) ($so->created_by ?: (auth()->id() ?: 1));

        $createdOutsideTx = DB::transactionLevel() === 0;

        $run = function () use ($so, $products, $business, $posSettings, $userId) {
            return $this->createFinalSellFromSalesOrder($so, $products, $business, $posSettings, $userId);
        };

        try {
            $sell = $createdOutsideTx ? DB::transaction($run) : $run();
        } catch (\Throwable $e) {
            Log::error('SalesOrderInvoiceService convert failed: '.$e->getMessage(), [
                'sales_order_id' => $so->id,
            ]);

            return [
                'status' => 'skipped',
                'reason' => 'error',
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return [
            'status' => 'created',
            'sell' => $sell,
            'msg' => __('lang_v1.so_invoice_created', ['invoice_no' => $sell->invoice_no]),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    private function createFinalSellFromSalesOrder(
        Transaction $so,
        array $products,
        Business $business,
        array $posSettings,
        int $userId
    ): Transaction {
        $neverPartiallyInvoiced = true;
        foreach ($so->sell_lines as $line) {
            if (! empty($line->parent_sell_line_id)) {
                continue;
            }
            if ((float) ($line->so_quantity_invoiced ?? 0) > 0) {
                $neverPartiallyInvoiced = false;
                break;
            }
        }

        if ($neverPartiallyInvoiced) {
            $finalTotal = (float) $so->final_total;
            $invoiceTotal = [
                'total_before_tax' => (float) $so->total_before_tax,
                'tax' => (float) $so->tax_amount,
            ];
            $discountAmount = (float) ($so->discount_amount ?? 0);
            $shippingCharges = (float) ($so->shipping_charges ?? 0);
        } else {
            $lineSumIncTax = 0.0;
            $lineSumBeforeTax = 0.0;
            $lineTax = 0.0;
            foreach ($products as $product) {
                $qty = (float) $product['quantity'];
                $lineSumIncTax += (float) $product['unit_price_inc_tax'] * $qty;
                $lineSumBeforeTax += (float) $product['unit_price'] * $qty;
                $lineTax += (float) $product['item_tax'] * $qty;
            }
            $finalTotal = $lineSumIncTax;
            $invoiceTotal = [
                'total_before_tax' => $lineSumBeforeTax,
                'tax' => $lineTax,
            ];
            $discountAmount = 0;
            $shippingCharges = 0;
        }

        $input = [
            'type' => 'sell',
            'status' => 'final',
            'location_id' => $so->location_id,
            'contact_id' => $so->contact_id,
            'customer_group_id' => $so->customer_group_id,
            'transaction_date' => now()->format('Y-m-d H:i:s'),
            'final_total' => $finalTotal,
            'discount_type' => $so->discount_type,
            'discount_amount' => $discountAmount,
            'tax_rate_id' => $so->tax_id,
            'sale_note' => $so->additional_notes,
            'staff_note' => $so->staff_note,
            'shipping_details' => $so->shipping_details,
            'shipping_address' => $so->shipping_address,
            'shipping_status' => $so->shipping_status,
            'shipping_charges' => $shippingCharges,
            'delivered_to' => $so->delivered_to,
            'delivery_person' => $so->delivery_person,
            'is_direct_sale' => 1,
            'sales_order_ids' => [$so->id],
            'commission_agent' => $so->commission_agent,
            'selling_price_group_id' => $so->selling_price_group_id,
            'exchange_rate' => $so->exchange_rate ?: 1,
            'products' => $products,
            'source' => $so->source,
        ];

        $sell = $this->transactionUtil->createSellTransaction(
            $so->business_id,
            $input,
            $invoiceTotal,
            $userId,
            false
        );

        $this->transactionUtil->createOrUpdateSellLines(
            $sell,
            $products,
            $so->location_id,
            false,
            null,
            [],
            false
        );

        foreach ($products as $product) {
            $decreaseQty = (float) $product['quantity'];
            if (! empty($product['enable_stock'])) {
                $this->productUtil->decreaseProductQuantity(
                    $product['product_id'],
                    $product['variation_id'],
                    $so->location_id,
                    $decreaseQty
                );
            }
            if (($product['product_type'] ?? '') === 'combo') {
                $comboDetails = $this->productUtil->resolveComboDetailsForStockAdjustment($product, false);
                if (! empty($comboDetails)) {
                    $this->productUtil->decreaseProductQuantityCombo($comboDetails, $so->location_id);
                }
            }
        }

        $sell->load('sell_lines');

        $mapBusiness = [
            'id' => $so->business_id,
            'accounting_method' => $business->accounting_method,
            'location_id' => $so->location_id,
            'pos_settings' => $posSettings,
        ];
        $this->transactionUtil->mapPurchaseSell($mapBusiness, $sell->sell_lines, 'purchase');

        // Move completed payments from SO → sell (do NOT fire TransactionPaymentAdded).
        $movedPaymentIds = TransactionPayment::where('transaction_id', $so->id)
            ->where('payment_line_status', 'completed')
            ->where('is_return', 0)
            ->pluck('id');

        if ($movedPaymentIds->isNotEmpty()) {
            TransactionPayment::whereIn('id', $movedPaymentIds)
                ->update(['transaction_id' => $sell->id]);

            // Keep cashbook rows aligned with the final invoice (no second credit).
            AccountTransaction::whereIn('transaction_payment_id', $movedPaymentIds)
                ->update(['transaction_id' => $sell->id]);
        }

        $this->transactionUtil->updatePaymentStatus($sell->id, $sell->final_total);
        $this->transactionUtil->updatePaymentStatus($so->id, $so->final_total);
        $this->transactionUtil->updateSalesOrderStatus([$so->id]);

        $this->transactionUtil->activityLog($sell, 'added');

        return $sell->fresh(['payment_lines', 'sell_lines']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildProductsPayload(Transaction $so): array
    {
        $products = [];

        foreach ($so->sell_lines as $line) {
            if (! empty($line->parent_sell_line_id)) {
                continue;
            }

            $remaining = (float) $line->quantity - (float) ($line->so_quantity_invoiced ?? 0);
            if ($remaining <= 0) {
                continue;
            }

            $productModel = $line->product ?: Product::find($line->product_id);
            $productType = $productModel->type ?? 'single';
            $enableStock = (int) ($productModel->enable_stock ?? 0);

            $row = [
                'product_id' => $line->product_id,
                'variation_id' => $line->variation_id,
                'quantity' => $remaining,
                'unit_price_before_discount' => $line->unit_price_before_discount,
                'unit_price' => $line->unit_price,
                'unit_price_inc_tax' => $line->unit_price_inc_tax,
                'item_tax' => $line->item_tax,
                'tax_id' => $line->tax_id,
                'line_discount_type' => $line->line_discount_type,
                'line_discount_amount' => $line->line_discount_amount,
                'sell_line_note' => $line->sell_line_note,
                'so_line_id' => $line->id,
                'enable_stock' => $enableStock,
                'product_type' => $productType,
            ];

            if ($productType === 'combo') {
                $comboChildren = $so->sell_lines
                    ->where('parent_sell_line_id', $line->id)
                    ->where('children_type', 'combo');

                if ($comboChildren->isNotEmpty()) {
                    $ratio = $line->quantity > 0 ? ($remaining / (float) $line->quantity) : 1;
                    $row['combo'] = $comboChildren->map(function ($child) use ($ratio) {
                        return [
                            'product_id' => $child->product_id,
                            'variation_id' => $child->variation_id,
                            'quantity' => (float) $child->quantity * $ratio,
                        ];
                    })->values()->all();
                } else {
                    $variation = Variation::find($line->variation_id);
                    $row['combo'] = $this->productUtil->buildComboSellLinePayload($variation, $remaining);
                }
            }

            $products[] = $row;
        }

        return $products;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    private function hasSufficientStock(int $locationId, array $products): bool
    {
        foreach ($products as $product) {
            if (! empty($product['enable_stock'])) {
                $available = (float) (VariationLocationDetails::where('variation_id', $product['variation_id'])
                    ->where('location_id', $locationId)
                    ->value('qty_available') ?? 0);
                if ($available < (float) $product['quantity']) {
                    return false;
                }
            }

            if (($product['product_type'] ?? '') === 'combo') {
                $comboDetails = $this->productUtil->resolveComboDetailsForStockAdjustment($product, false);
                foreach ($comboDetails as $combo) {
                    $childProduct = Product::find($combo['product_id']);
                    if (empty($childProduct) || empty($childProduct->enable_stock)) {
                        continue;
                    }
                    $available = (float) (VariationLocationDetails::where('variation_id', $combo['variation_id'])
                        ->where('location_id', $locationId)
                        ->value('qty_available') ?? 0);
                    if ($available < (float) $combo['quantity']) {
                        return false;
                    }
                }
            }
        }

        return true;
    }
}
