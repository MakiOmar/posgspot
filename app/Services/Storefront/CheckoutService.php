<?php

namespace App\Services\Storefront;

use App\Contact;
use App\Transaction;
use App\Services\Storefront\Payment\PaymentGatewayManager;
use App\Coupon;
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
        private RewardPointsService $rewardPointsService,
        private CouponService $couponService,
        private PaymentGatewayManager $paymentGateways,
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
            return $this->appendPaymentSession($businessId, $existing, $payload);
        }

        $locationIds = $this->storefrontSettings->getSellingLocationIds($businessId);
        $locationId = (int) ($payload['location_id'] ?? $this->storefrontSettings->get($businessId)['default_fulfillment_location_id'] ?? 0);

        if (empty($locationIds) || ! in_array($locationId, $locationIds, true)) {
            throw ValidationException::withMessages(['location_id' => ['Invalid or unconfigured fulfillment location.']]);
        }

        $couponCode = isset($payload['coupon_code']) ? trim((string) $payload['coupon_code']) : null;
        if ($couponCode === '') {
            $couponCode = null;
        }

        $couponCodes = $this->couponService->normalizeCodes($couponCode, $payload['coupon_codes'] ?? null);

        if ($couponCodes !== [] && empty($authContact)) {
            throw ValidationException::withMessages(['coupon_code' => ['Sign in to apply a promo code.']]);
        }

        $validated = $this->cartValidation->validate(
            $businessId,
            $payload['items'] ?? [],
            $locationId,
            $couponCode,
            $authContact,
            $payload['coupon_codes'] ?? null
        );
        $settings = $this->storefrontSettings->get($businessId);
        $paymentMethod = $this->normalizePaymentMethod($payload['payment_method'] ?? 'cod');

        if ($paymentMethod === 'cod' && empty($settings['cod_enabled'])) {
            throw ValidationException::withMessages(['payment_method' => ['Cash on delivery is not available.']]);
        }

        if ($paymentMethod === 'fawry') {
            $this->assertFawryEnabled($settings);
        }

        $contact = $authContact ?? $this->resolveGuestContact($businessId, $payload['customer'] ?? []);

        $couponDiscount = (float) ($validated['coupon_discount'] ?? 0);
        $couponId = $validated['coupon_id'] ?? null;
        $stackWithRewardPoints = (bool) ($validated['stack_with_reward_points'] ?? true);

        $requestedRewardPoints = (int) ($payload['reward_points'] ?? 0);
        $rpRedeemed = 0;
        $rpRedeemedAmount = 0.0;
        $orderTotalBeforeRedeem = (float) $validated['total'];

        if ($requestedRewardPoints > 0) {
            if (empty($authContact)) {
                throw ValidationException::withMessages(['reward_points' => ['Sign in to redeem reward points.']]);
            }

            if (! empty($validated['applied_coupons']) && ! $stackWithRewardPoints) {
                throw ValidationException::withMessages([
                    'reward_points' => ['Reward points cannot be combined with this promo code.'],
                ]);
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
                'discount_amount' => $couponDiscount,
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
            if (! empty($validated['coupon_ids'])) {
                $transaction->storefront_coupon_id = (int) $validated['coupon_ids'][0];
                $codes = $validated['coupon_codes'] ?? [];
                if ($codes === [] && ! empty($validated['coupon']['code'])) {
                    $codes = [$validated['coupon']['code']];
                }
                $transaction->storefront_coupon_code = implode(', ', $codes);
            }
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

            if (! empty($validated['applied_coupons']) && $authContact) {
                $this->persistCouponRedemptions(
                    $businessId,
                    $transaction,
                    $authContact,
                    $validated,
                    $settings
                );
            }

            DB::commit();

            $transaction = $transaction->fresh(['sell_lines', 'contact']);

            return $this->appendPaymentSession($businessId, $transaction, $payload);
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

    private function normalizePaymentMethod(string $method): string
    {
        $method = strtolower(trim($method));

        if (in_array($method, ['card', 'fawry', 'online'], true)) {
            return 'fawry';
        }

        return 'cod';
    }

    private function assertFawryEnabled(array $settings): void
    {
        $gateway = $settings['gateway'] ?? [];
        if (empty($gateway['enabled']) || ($gateway['provider'] ?? '') !== 'fawry') {
            throw ValidationException::withMessages(['payment_method' => ['Online payments are not available.']]);
        }

        $merchantCode = trim((string) ($gateway['fawry']['merchant_code'] ?? ''));
        if ($merchantCode === '') {
            throw ValidationException::withMessages(['payment_method' => ['Fawry is not configured.']]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function persistCouponRedemptions(
        int $businessId,
        Transaction $transaction,
        Contact $contact,
        array $validated,
        array $settings
    ): void {
        $appliedCoupons = $validated['applied_coupons'] ?? [];
        if ($appliedCoupons === []) {
            return;
        }

        $couponLines = array_map(fn ($line) => [
            'variation_id' => (int) $line['variation_id'],
            'product_id' => (int) ($line['product_id'] ?? 0),
            'category_id' => $line['category_id'] ?? null,
            'line_total' => (float) $line['line_total'],
            'on_sale' => (bool) ($line['on_sale'] ?? false),
        ], $validated['lines']);

        $codes = array_map(
            fn (array $applied) => (string) ($applied['code'] ?? ''),
            $appliedCoupons
        );
        $reapplied = $this->couponService->applyMultipleToCart(
            $businessId,
            $codes,
            $couponLines,
            (float) $validated['subtotal'],
            $settings,
            $contact,
            Coupon::CHANNEL_STOREFRONT
        );

        $totalDiscount = 0.0;
        foreach ($reapplied['applied_coupons'] ?? [] as $applied) {
            $couponId = (int) ($applied['coupon_id'] ?? 0);
            if ($couponId <= 0) {
                continue;
            }

            if ($this->couponService->hasExistingRedemption($couponId, $transaction->id)) {
                $totalDiscount += (float) ($applied['coupon_discount'] ?? 0);

                continue;
            }

            $coupon = $this->couponService->lockForCheckout($couponId);
            $discountAmount = (float) ($applied['coupon_discount'] ?? 0);

            $this->couponService->recordRedemption(
                $coupon,
                $transaction,
                $contact,
                $discountAmount,
                Coupon::CHANNEL_STOREFRONT
            );

            $totalDiscount += $discountAmount;
        }

        $transaction->discount_amount = round($totalDiscount, 4);
        $transaction->storefront_coupon_id = $reapplied['coupon_id'] ?? null;
        $transaction->storefront_coupon_code = implode(', ', $reapplied['coupon_codes'] ?? []);
        $transaction->save();
    }

    private function appendPaymentSession(int $businessId, Transaction $transaction, array $payload): array
    {
        $response = $this->formatOrderResponse($transaction);
        $paymentMethod = $this->normalizePaymentMethod($payload['payment_method'] ?? 'cod');

        if ($paymentMethod !== 'fawry') {
            return $response;
        }

        if (strtolower(trim((string) $transaction->payment_status)) === 'paid') {
            return $response;
        }

        $config = $this->paymentGateways->gatewayConfig($businessId);
        $driver = $this->paymentGateways->driver('fawry');
        $locale = in_array($payload['locale'] ?? 'en', ['en', 'ar'], true) ? $payload['locale'] : 'en';
        $returnUrl = $this->buildPaymentReturnUrl($locale, (string) $transaction->storefront_order_id);
        $response['payment'] = $driver->buildChargeSession($transaction, $config, $returnUrl, $locale);

        return $response;
    }

    private function buildPaymentReturnUrl(string $locale, string $storefrontOrderId): string
    {
        $base = rtrim((string) config('storefront.url'), '/');
        $lang = $locale === 'ar' ? 'ar' : 'en';

        return $base.'/'.$lang.'/checkout/payment/return/?order='.urlencode($storefrontOrderId);
    }
}
