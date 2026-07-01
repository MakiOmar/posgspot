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
        private ProductUtil $productUtil,
        private RewardPointsService $rewardPointsService
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

        $requestedRewardPoints = (int) ($payload['reward_points'] ?? 0);
        $rpRedeemed = 0;
        $rpRedeemedAmount = 0.0;
        $orderTotalBeforeRedeem = (float) $validated['total'];

        if ($requestedRewardPoints > 0) {
            if (empty($authContact)) {
                throw ValidationException::withMessages(['reward_points' => ['Sign in to redeem reward points.']]);
            }

            $redemption = $this->rewardPointsService->resolveCheckoutRedemption(
                $businessId,
                $authContact,
                $requestedRewardPoints,
                $orderTotalBeforeRedeem
            );
            $rpRedeemed = $redemption['points'];
            $rpRedeemedAmount = $redemption['amount'];
        }

        DB::beginTransaction();

        try {
            if ($rpRedeemed > 0) {
                $contact = Contact::where('business_id', $businessId)
                    ->where('id', $contact->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $redemption = $this->rewardPointsService->resolveCheckoutRedemption(
                    $businessId,
                    $contact,
                    $requestedRewardPoints,
                    $orderTotalBeforeRedeem
                );
                $rpRedeemed = $redemption['points'];
                $rpRedeemedAmount = $redemption['amount'];
            }

            $shipping = (float) $validated['shipping'];
            $subtotal = (float) $validated['subtotal'];
            $finalTotal = max(0, round($orderTotalBeforeRedeem - $rpRedeemedAmount, 4));

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
                'rp_redeemed' => $rpRedeemed,
                'rp_redeemed_amount' => $rpRedeemedAmount,
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

            if ($rpRedeemed > 0 && $this->rewardPointsService->isEnabled($businessId)) {
                $this->transactionUtil->updateCustomerRewardPoints(
                    $contact->id,
                    $transaction->rp_earned,
                    0,
                    $rpRedeemed
                );
            }

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

    /**
     * Structured shipping address for account order detail (storefront + legacy POS keys).
     */
    public function shippingAddressPayload(Transaction $transaction): ?array
    {
        $addresses = ! empty($transaction->order_addresses)
            ? json_decode($transaction->order_addresses, true)
            : [];

        $raw = is_array($addresses) ? ($addresses['shipping_address'] ?? []) : [];

        if (empty($raw) && ! empty($transaction->shipping_address)) {
            return [
                'address_line_1' => null,
                'address_line_2' => null,
                'city' => null,
                'state' => null,
                'country' => null,
                'zip_code' => null,
                'formatted' => trim((string) $transaction->shipping_address),
            ];
        }

        if (empty($raw)) {
            return null;
        }

        $normalized = [
            'address_line_1' => $raw['address_line_1'] ?? $raw['shipping_address_line_1'] ?? null,
            'address_line_2' => $raw['address_line_2'] ?? $raw['shipping_address_line_2'] ?? null,
            'city' => $raw['city'] ?? $raw['shipping_city'] ?? null,
            'state' => $raw['state'] ?? $raw['shipping_state'] ?? null,
            'country' => $raw['country'] ?? $raw['shipping_country'] ?? null,
            'zip_code' => $raw['zip_code'] ?? $raw['shipping_zip_code'] ?? null,
        ];

        $formatted = $this->formatAddressString($normalized);
        if ($formatted === '') {
            return null;
        }

        $normalized['formatted'] = $formatted;

        return $normalized;
    }

    public function listOrdersForContact(int $businessId, int $contactId): array
    {
        return $this->contactOrdersQuery($businessId, $contactId)
            ->orderByDesc('transaction_date')
            ->get()
            ->map(fn ($t) => $this->formatOrderResponse($t))
            ->all();
    }

    public function getOrderForContact(int $businessId, int $contactId, int $orderId): ?array
    {
        $transaction = $this->contactOrdersQuery($businessId, $contactId)
            ->with(['sell_lines', 'location'])
            ->where('id', $orderId)
            ->first();

        if (empty($transaction)) {
            return null;
        }

        $transaction->load(['sell_lines.product', 'sell_lines.variations']);

        $data = $this->formatOrderResponse($transaction);
        $data['shipping_address'] = $this->shippingAddressPayload($transaction);
        $data['fulfillment_location'] = $transaction->location->name ?? null;
        $data['lines'] = $transaction->sell_lines->map(fn ($line) => [
            'product_id' => $line->product_id,
            'variation_id' => $line->variation_id,
            'product_name' => $line->product->name ?? null,
            'variation_name' => $line->variations->name ?? null,
            'quantity' => (float) $line->quantity,
            'unit_price_inc_tax' => (float) $line->unit_price_inc_tax,
            'line_total' => (float) $line->quantity * (float) $line->unit_price_inc_tax,
        ])->values()->all();
        $data['invoice_print_url'] = $this->invoicePrintUrl($businessId, $transaction);

        return $data;
    }

    public function invoicePrintUrlForContact(int $businessId, int $contactId, int $orderId): ?string
    {
        $transaction = $this->contactOrdersQuery($businessId, $contactId)
            ->where('id', $orderId)
            ->first();

        if (empty($transaction)) {
            return null;
        }

        return $this->invoicePrintUrl($businessId, $transaction);
    }

    /**
     * Final sell transactions for this contact (storefront checkout and POS sales).
     */
    private function contactOrdersQuery(int $businessId, int $contactId)
    {
        return Transaction::where('business_id', $businessId)
            ->where('contact_id', $contactId)
            ->where('type', 'sell')
            ->where('status', 'final');
    }

    /**
     * Same receipt page as POS invoice print ({@see SellPosController::showInvoice}).
     */
    private function invoicePrintUrl(int $businessId, Transaction $transaction): ?string
    {
        if (strtolower(trim((string) $transaction->payment_status)) !== 'paid') {
            return null;
        }

        $url = $this->transactionUtil->getInvoiceUrl($transaction->id, $businessId);

        return $url.'?print_on_load=true';
    }
}
