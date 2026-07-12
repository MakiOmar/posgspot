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
            $payload['coupon_codes'] ?? null,
            $payload['shipping_address'] ?? [],
            $payload['shipping_rate_id'] ?? null,
            (string) ($payload['locale'] ?? 'en'),
            true
        );
        // Prefer validator-enriched items (Accounts catalog price backfilled like send-to-POS).
        $checkoutItems = is_array($validated['items'] ?? null) && $validated['items'] !== []
            ? $validated['items']
            : ($payload['items'] ?? []);
        $validated = $this->forceDigitalPricesOnValidated($validated, $checkoutItems);
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
                // Same pattern as AccountsApi::formatOrderToSale (game details on staff_note).
                'staff_note' => $this->digitalStaffNote($checkoutItems),
                'shipping_details' => $validated['shipping_rate']['title']
                    ?? ($payload['shipping_method'] ?? 'Delivery'),
                'shipping_charges' => $shipping,
                'shipping_address' => $shippingAddress,
                'shipping_status' => 'ordered',
                'storefront_shipping_meta' => [
                    'rate_id' => $payload['shipping_rate_id'] ?? null,
                    'rate' => $validated['shipping_rate'] ?? null,
                    'matched_zone_id' => $validated['matched_zone_id'] ?? null,
                ],
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

            $hasDigitalCheckout = false;
            foreach ($checkoutItems as $ci) {
                if (is_array($ci) && ! empty(($ci['digital']['kind'] ?? null))) {
                    $hasDigitalCheckout = true;
                    break;
                }
            }
            $priceDebugInput = StorefrontPriceDebug::snapshotCheckoutInput(
                $checkoutItems,
                $input['products'],
                $subtotal,
                $finalTotal
            );
            if ($hasDigitalCheckout || StorefrontPriceDebug::enabled()) {
                StorefrontPriceDebug::log('checkout.before_create', array_merge($priceDebugInput, [
                    'order_id' => $orderId,
                    'location_id' => $locationId,
                    'payment_method' => $paymentMethod,
                    'has_digital' => $hasDigitalCheckout,
                ]));
            }

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

            if ($hasDigitalCheckout || StorefrontPriceDebug::enabled()) {
                StorefrontPriceDebug::log('checkout.after_transaction', [
                    'transaction_id' => $transaction->id,
                    'invoice_no' => $transaction->invoice_no,
                    'total_before_tax' => $transaction->total_before_tax,
                    'final_total' => $transaction->final_total,
                ]);
            }

            $this->transactionUtil->createOrUpdateSellLines($transaction, $input['products'], $locationId, false, null, [], false);

            $afterLines = StorefrontPriceDebug::snapshotTransaction($transaction);
            if ($hasDigitalCheckout || StorefrontPriceDebug::enabled()) {
                StorefrontPriceDebug::log('checkout.after_sell_lines', $afterLines);
            }

            $this->syncDigitalSellLinePrices($transaction, $checkoutItems, $input['products'], $couponDiscount, $rpRedeemedAmount);

            $afterSync = StorefrontPriceDebug::snapshotTransaction($transaction);
            if ($hasDigitalCheckout || StorefrontPriceDebug::enabled()) {
                StorefrontPriceDebug::log('checkout.after_price_sync', $afterSync);
            }
            if (! empty($afterSync['any_line_zero'])) {
                StorefrontPriceDebug::log('checkout.ZERO_PRICE_AFTER_SYNC', [
                    'transaction_id' => $transaction->id,
                    'invoice_no' => $transaction->invoice_no,
                    'input' => $priceDebugInput,
                    'db' => $afterSync,
                ]);
            }

            $this->queueDigitalFulfillments($transaction, $checkoutItems);

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
            $finalSnap = StorefrontPriceDebug::snapshotTransaction($transaction);
            if ($hasDigitalCheckout || StorefrontPriceDebug::enabled()) {
                StorefrontPriceDebug::log('checkout.after_commit', $finalSnap);
            }

            $response = $this->appendPaymentSession($businessId, $transaction, $payload);
            if (StorefrontPriceDebug::enabled()) {
                $response['_price_debug'] = [
                    'input' => $priceDebugInput,
                    'after_sell_lines' => $afterLines,
                    'after_price_sync' => $afterSync,
                    'after_commit' => $finalSnap,
                ];
            }

            return $response;
        } catch (\Throwable $e) {
            DB::rollBack();
            StorefrontPriceDebug::log('checkout.exception', [
                'order_id' => $orderId ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
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
            'district_id' => $raw['district_id'] ?? $raw['districtId'] ?? null,
            'district_label' => $raw['district_label'] ?? null,
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
        $data['subtotal'] = (float) $transaction->total_before_tax;
        $data['discount_amount'] = (float) $transaction->discount_amount;
        $data['discount_type'] = $transaction->discount_type;
        $data['shipping_charges'] = (float) $transaction->shipping_charges;
        $data['coupon_code'] = $transaction->storefront_coupon_code;
        $data['shipping_method'] = $transaction->shipping_details;
        $data['shipping_carrier'] = $transaction->shipping_carrier;
        $data['shipping_tracking_number'] = $transaction->shipping_tracking_number;
        $data['shipping_tracking_url'] = $transaction->shipping_tracking_url;
        $meta = $transaction->storefront_shipping_meta;
        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }
        $data['shipping_meta'] = is_array($meta) ? $meta : null;
        $data['lines'] = $transaction->sell_lines->map(fn ($line) => [
            'product_id' => $line->product_id,
            'variation_id' => $line->variation_id,
            'product_name' => $line->product->name ?? null,
            'variation_name' => $line->variations->name ?? null,
            'slug' => $line->product->slug ?? null,
            'image_url' => $line->product->image_url ?? null,
            'quantity' => (float) $line->quantity,
            'unit_price_inc_tax' => (float) $line->unit_price_inc_tax,
            'line_total' => (float) $line->quantity * (float) $line->unit_price_inc_tax,
        ])->values()->all();
        $data['invoice_print_url'] = $this->invoicePrintUrl($businessId, $transaction);
        $data['digital_deliveries'] = app(DigitalFulfillmentService::class)
            ->customerDeliveriesForTransaction($transaction);

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

    /**
     * Ensure products_payload / totals use Accounts catalog prices for digital lines
     * (same idea as AccountsApi: unit_price = line_total / quantity).
     *
     * @param  array<string, mixed>  $validated
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function forceDigitalPricesOnValidated(array $validated, array $items): array
    {
        $hasDigital = false;
        $subtotal = 0.0;

        foreach ($validated['products_payload'] as $i => $product) {
            $item = is_array($items[$i] ?? null) ? $items[$i] : [];
            $digital = is_array($item['digital'] ?? null) ? $item['digital'] : null;
            $qty = (float) ($product['quantity'] ?? 0);

            if ($digital && ! empty($digital['kind'])) {
                $hasDigital = true;
                $price = null;
                foreach ([$digital['price'] ?? null, $item['unit_price'] ?? null, $item['price'] ?? null, $product['unit_price_inc_tax'] ?? null] as $candidate) {
                    if ($candidate !== null && $candidate !== '' && is_numeric($candidate) && (float) $candidate > 0) {
                        $price = (float) $candidate;
                        break;
                    }
                }
                if ($price === null) {
                    throw ValidationException::withMessages([
                        "items.$i.digital.price" => ['Digital item price is required.'],
                    ]);
                }
                // AccountsApi: $unit_price = $product_line->total / $product_line->quantity
                $lineTotal = $price * max($qty, 1);
                $unitPrice = $qty > 0 ? ($lineTotal / $qty) : $price;
                $validated['products_payload'][$i]['unit_price'] = $unitPrice;
                $validated['products_payload'][$i]['unit_price_inc_tax'] = $unitPrice;
                $validated['products_payload'][$i]['item_tax'] = 0;
                $validated['products_payload'][$i]['tax_id'] = null;
                if (! empty($digital['title']) && empty($validated['products_payload'][$i]['sell_line_note'])) {
                    $validated['products_payload'][$i]['sell_line_note'] = (string) $digital['title'];
                }
                $subtotal += $unitPrice * $qty;
            } else {
                $subtotal += (float) ($product['unit_price_inc_tax'] ?? 0) * $qty;
            }
        }

        if (! $hasDigital) {
            return $validated;
        }

        foreach ($validated['lines'] as $i => $line) {
            $item = is_array($items[$i] ?? null) ? $items[$i] : [];
            $digital = is_array($item['digital'] ?? null) ? $item['digital'] : null;
            if (! $digital || empty($digital['kind'])) {
                continue;
            }
            $price = (float) ($validated['products_payload'][$i]['unit_price_inc_tax'] ?? 0);
            $qty = (float) ($line['quantity'] ?? 0);
            $validated['lines'][$i]['unit_price'] = $price;
            $validated['lines'][$i]['line_total'] = $price * $qty;
        }

        $shipping = (float) ($validated['shipping'] ?? 0);
        $couponDiscount = (float) ($validated['coupon_discount'] ?? 0);
        $validated['subtotal'] = round($subtotal, 4);
        $validated['total'] = round(max(0, $subtotal + $shipping - $couponDiscount), 4);

        if ($validated['subtotal'] <= 0) {
            throw ValidationException::withMessages([
                'items' => ['Digital order total cannot be zero. Catalog price is missing.'],
            ]);
        }

        return $validated;
    }

    /**
     * Patch sell lines + transaction totals using checkout item index
     * (Accounts send-to-POS style — never trust POS placeholder SKU price).
     *
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $products
     */
    private function syncDigitalSellLinePrices(
        Transaction $transaction,
        array $items,
        array $products,
        float $couponDiscount,
        float $rpRedeemedAmount
    ): void {
        $sellLines = $transaction->sell_lines()
            ->whereNull('parent_sell_line_id')
            ->orderBy('id')
            ->get()
            ->values();

        $changed = false;
        $hasDigital = false;

        foreach ($products as $i => $product) {
            $item = is_array($items[$i] ?? null) ? $items[$i] : [];
            $digital = is_array($item['digital'] ?? null) ? $item['digital'] : null;
            if (! $digital || empty($digital['kind'])) {
                continue;
            }
            $hasDigital = true;
            $price = (float) ($product['unit_price_inc_tax'] ?? $product['unit_price'] ?? 0);
            if ($price <= 0) {
                foreach ([$digital['price'] ?? null, $item['unit_price'] ?? null, $item['price'] ?? null] as $candidate) {
                    if ($candidate !== null && $candidate !== '' && is_numeric($candidate) && (float) $candidate > 0) {
                        $price = (float) $candidate;
                        break;
                    }
                }
            }
            if ($price <= 0) {
                continue;
            }

            $line = $sellLines->get($i);
            if (! $line) {
                continue;
            }

            DB::table('transaction_sell_lines')->where('id', $line->id)->update([
                'unit_price_before_discount' => $price,
                'unit_price' => $price,
                'unit_price_inc_tax' => $price,
                'item_tax' => 0,
                'tax_id' => null,
                'sell_line_note' => ! empty($digital['title'])
                    ? (string) $digital['title']
                    : (string) ($line->sell_line_note ?? ''),
            ]);
            $changed = true;
        }

        if (! $hasDigital) {
            return;
        }

        if (! $changed && (float) $transaction->final_total > 0) {
            return;
        }

        $transaction->load('sell_lines');
        $subtotal = 0.0;
        foreach ($transaction->sell_lines as $line) {
            if (! empty($line->parent_sell_line_id)) {
                continue;
            }
            $subtotal += (float) $line->unit_price_inc_tax * (float) $line->quantity;
        }
        $shipping = (float) $transaction->shipping_charges;
        $finalTotal = max(0, round($subtotal + $shipping - $couponDiscount - $rpRedeemedAmount, 4));
        $transaction->total_before_tax = round($subtotal, 4);
        $transaction->final_total = $finalTotal;
        $transaction->save();

        if ($finalTotal <= 0) {
            throw ValidationException::withMessages([
                'items' => ['Digital order total cannot be zero after POS insert.'],
            ]);
        }
    }

    /**
     * Staff note block matching AccountsApi game meta summary.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function digitalStaffNote(array $items): ?string
    {
        $blocks = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $digital = is_array($item['digital'] ?? null) ? $item['digital'] : null;
            if (! $digital || empty($digital['kind'])) {
                continue;
            }
            $title = (string) ($digital['title'] ?? 'N/A');
            $type = (string) ($digital['type'] ?? ($digital['kind'] === 'card' ? 'card' : 'N/A'));
            $blocks[] = 'Game Title: '.$title."\nType: ".$type."\nAccount: N/A\nPassword: N/A<br>----------------------<br>";
        }

        if ($blocks === []) {
            return null;
        }

        return implode("\n", $blocks);
    }

    /**
     * Queue digital game/card allocation rows (secrets allocated only after paid).
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function queueDigitalFulfillments(Transaction $transaction, array $items): void
    {
        $digitalItems = [];
        $sellLines = $transaction->sell_lines()
            ->whereNull('parent_sell_line_id')
            ->orderBy('id')
            ->get()
            ->values();

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $digital = $item['digital'] ?? null;
            if (! is_array($digital) || empty($digital['kind'])) {
                continue;
            }
            $kind = ($digital['kind'] ?? '') === 'card' ? 'card' : 'game';
            $variationId = (int) ($item['variation_id'] ?? 0);
            $sellLine = $sellLines->get($index);
            $lineKey = (string) ($digital['line_key'] ?? '');
            if ($lineKey === '') {
                $lineKey = $kind === 'card'
                    ? 'card|category:'.($digital['card_category_id'] ?? '0')
                    : 'ps'.($digital['platform'] ?? '4').'_'.($digital['type'] ?? 'primary').'_stock|game:'.($digital['game_id'] ?? '0');
            }
            $digitalItems[] = array_merge($digital, [
                'kind' => $kind,
                'line_key' => $lineKey,
                'sell_line_id' => $sellLine?->id,
                'variation_id' => $variationId,
                'price' => $digital['price'] ?? null,
                'title' => $digital['title'] ?? null,
            ]);
        }

        if ($digitalItems === []) {
            return;
        }

        app(DigitalFulfillmentService::class)->queuePending($transaction, $digitalItems);
    }
}
