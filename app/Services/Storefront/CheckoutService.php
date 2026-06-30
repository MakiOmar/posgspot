<?php

namespace App\Services\Storefront;

use App\Contact;
use App\Transaction;
use App\Utils\ContactUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates POS sell transactions from storefront checkout (no WooCommerce module).
 */
class CheckoutService
{
    public function __construct(
        private StorefrontSettingService $storefrontSettings,
        private CartValidationService $cartValidation,
        private ContactUtil $contactUtil,
        private TransactionUtil $transactionUtil,
        private ProductUtil $productUtil
    ) {
    }

    public function checkout(int $businessId, array $payload, ?Contact $authContact = null): array
    {
        $orderId = $payload['storefront_order_id'] ?? $payload['idempotency_key'] ?? null;
        if (empty($orderId)) {
            throw ValidationException::withMessages(['idempotency_key' => ['Idempotency key is required.']]);
        }

        $existing = Transaction::where('business_id', $businessId)
            ->where('storefront_order_id', $orderId)
            ->first();

        if ($existing) {
            return $this->formatOrderResponse($existing);
        }

        $locationIds = $this->storefrontSettings->getSellingLocationIds($businessId);
        $locationId = (int) ($payload['location_id'] ?? $this->storefrontSettings->get($businessId)['default_fulfillment_location_id'] ?? 0);

        if (empty($locationIds) || ! in_array($locationId, $locationIds, true)) {
            throw ValidationException::withMessages(['location_id' => ['Invalid or unconfigured fulfillment location.']]);
        }

        $validated = $this->cartValidation->validate($businessId, $payload['items'] ?? [], $locationId);
        $settings = $this->storefrontSettings->get($businessId);
        $paymentMethod = $payload['payment_method'] ?? 'cod';

        if ($paymentMethod === 'cod' && empty($settings['cod_enabled'])) {
            throw ValidationException::withMessages(['payment_method' => ['Cash on delivery is not available.']]);
        }

        $contact = $authContact ?? $this->resolveGuestContact($businessId, $payload['customer'] ?? []);

        DB::beginTransaction();

        try {
            $shipping = (float) $validated['shipping'];
            $subtotal = (float) $validated['subtotal'];
            $finalTotal = (float) $validated['total'];

            $invoiceTotal = [
                'total_before_tax' => $subtotal,
                'tax' => 0,
            ];

            $shippingAddress = $this->formatAddressString($payload['shipping_address'] ?? []);
            $orderAddresses = json_encode([
                'shipping_address' => $payload['shipping_address'] ?? [],
                'billing_address' => $payload['billing_address'] ?? $payload['shipping_address'] ?? [],
            ]);

            $input = [
                'location_id' => $locationId,
                'contact_id' => $contact->id,
                'status' => 'final',
                'transaction_date' => now()->format('Y-m-d H:i:s'),
                'final_total' => $finalTotal,
                'discount_type' => 'fixed',
                'discount_amount' => 0,
                'tax_rate_id' => null,
                'products' => $validated['products_payload'],
                'source' => 'storefront',
                'sale_note' => $payload['order_note'] ?? null,
                'shipping_details' => $payload['shipping_method'] ?? 'Delivery',
                'shipping_charges' => $shipping,
                'shipping_address' => $shippingAddress,
                'shipping_status' => 'ordered',
                'order_addresses' => $orderAddresses,
                'is_created_from_api' => 1,
            ];

            if ($paymentMethod === 'cod') {
                $input['payment'] = [];
            } else {
                $input['payment'] = [
                    [
                        'amount' => $finalTotal,
                        'method' => 'card',
                        'payment_line_status' => 'pending',
                    ],
                ];
            }

            $userId = 1;
            $transaction = $this->transactionUtil->createSellTransaction($businessId, $input, $invoiceTotal, $userId, false);
            $transaction->storefront_order_id = $orderId;
            $transaction->save();

            $this->transactionUtil->createOrUpdateSellLines($transaction, $input['products'], $locationId, false, null, [], false);

            if ($paymentMethod !== 'cod') {
                $this->transactionUtil->createOrUpdatePaymentLines($transaction, $input['payment'], $businessId, $userId, false);
            }

            foreach ($input['products'] as $product) {
                $decreaseQty = (float) $product['quantity'];
                if ($product['enable_stock']) {
                    $this->productUtil->decreaseProductQuantity(
                        $product['product_id'],
                        $product['variation_id'],
                        $locationId,
                        $decreaseQty
                    );
                }
                if (($product['product_type'] ?? '') === 'combo') {
                    $comboDetails = $this->productUtil->resolveComboDetailsForStockAdjustment($product, false);
                    $this->productUtil->decreaseProductQuantityCombo($comboDetails, $locationId);
                }
            }

            $transaction->payment_status = $paymentMethod === 'cod' ? 'due' : 'due';
            $transaction->save();

            DB::commit();

            return $this->formatOrderResponse($transaction->fresh(['sell_lines']));
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function resolveGuestContact(int $businessId, array $customer): Contact
    {
        $email = $customer['email'] ?? null;
        $mobile = $customer['mobile'] ?? $customer['phone'] ?? null;
        $name = trim(($customer['first_name'] ?? '').' '.($customer['last_name'] ?? ''));
        if ($name === '') {
            $name = $customer['name'] ?? 'Guest';
        }

        $query = Contact::where('business_id', $businessId)->whereIn('type', ['customer', 'both']);
        if ($email) {
            $existing = (clone $query)->where('email', $email)->first();
            if ($existing) {
                return $existing;
            }
        }
        if ($mobile) {
            $existing = (clone $query)->where('mobile', $mobile)->first();
            if ($existing) {
                return $existing;
            }
        }

        return $this->contactUtil->createNewContact([
            'business_id' => $businessId,
            'type' => 'customer',
            'contact_status' => 'active',
            'name' => $name,
            'first_name' => $customer['first_name'] ?? null,
            'last_name' => $customer['last_name'] ?? null,
            'email' => $email,
            'mobile' => $mobile ?? '',
            'created_by' => 1,
        ])['data'];
    }

    private function formatAddressString(array $address): string
    {
        return implode(', ', array_filter([
            $address['address_line_1'] ?? null,
            $address['address_line_2'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
            $address['country'] ?? null,
            $address['zip_code'] ?? null,
        ]));
    }

    public function formatOrderResponse(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'storefront_order_id' => $transaction->storefront_order_id,
            'invoice_no' => $transaction->invoice_no,
            'status' => $transaction->status,
            'payment_status' => $transaction->payment_status,
            'final_total' => (float) $transaction->final_total,
            'transaction_date' => $transaction->transaction_date,
            'shipping_status' => $transaction->shipping_status,
        ];
    }

    public function listOrdersForContact(int $businessId, int $contactId): array
    {
        return Transaction::where('business_id', $businessId)
            ->where('contact_id', $contactId)
            ->where('type', 'sell')
            ->where('source', 'storefront')
            ->orderByDesc('transaction_date')
            ->get()
            ->map(fn ($t) => $this->formatOrderResponse($t))
            ->all();
    }

    public function getOrderForContact(int $businessId, int $contactId, int $orderId): ?array
    {
        $transaction = Transaction::with('sell_lines')
            ->where('business_id', $businessId)
            ->where('contact_id', $contactId)
            ->where('id', $orderId)
            ->where('source', 'storefront')
            ->first();

        if (empty($transaction)) {
            return null;
        }

        $data = $this->formatOrderResponse($transaction);
        $data['lines'] = $transaction->sell_lines->map(fn ($line) => [
            'product_id' => $line->product_id,
            'variation_id' => $line->variation_id,
            'quantity' => (float) $line->quantity,
            'unit_price_inc_tax' => (float) $line->unit_price_inc_tax,
            'line_total' => (float) $line->quantity * (float) $line->unit_price_inc_tax,
        ])->values()->all();

        return $data;
    }
}
