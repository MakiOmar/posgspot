<?php

namespace App\Services\Storefront\Shipping\Carriers;

use App\StorefrontShipment;
use App\Transaction;
use Illuminate\Support\Facades\Log;

/**
 * Bosta Egypt courier adapter — payload/endpoints match bosta-woocommerce.
 */
class BostaShippingCarrier implements ShippingCarrierInterface
{
    public function __construct(private BostaApiClient $api)
    {
    }

    public function key(): string
    {
        return 'bosta';
    }

    public function isEnabled(int $businessId): bool
    {
        return $this->api->isConfigured($businessId);
    }

    public function createShipment(int $businessId, Transaction $transaction): ?array
    {
        if (! $this->isEnabled($businessId)) {
            return null;
        }

        $transaction->loadMissing(['contact', 'sell_lines.product', 'payment_lines']);

        $delivery = $this->buildDeliveryPayload($businessId, $transaction);
        if ($delivery === null) {
            return null;
        }

        $response = $this->api->request($businessId, 'POST', 'deliveries/bulk', [
            'deliveries' => [$delivery],
            'deleteFailedDeliveries' => false,
        ]);

        if (! $response['success']) {
            Log::warning('Bosta createShipment failed', [
                'transaction_id' => $transaction->id,
                'error' => $response['error'],
                'body' => $response['body'],
            ]);

            return null;
        }

        $data = $response['body']['data'] ?? [];
        $failed = $data['failedDeliveries'] ?? [];
        if (! empty($failed)) {
            Log::warning('Bosta createShipment rejected delivery', [
                'transaction_id' => $transaction->id,
                'failed' => $failed,
            ]);

            return null;
        }

        $createdIds = $data['createdDeliveriesIds'] ?? [];
        if (! is_array($createdIds) || $createdIds === []) {
            Log::warning('Bosta createShipment missing createdDeliveriesIds', [
                'transaction_id' => $transaction->id,
                'data' => $data,
            ]);

            return null;
        }

        $deliveryId = (string) $createdIds[0];
        $hydrated = $this->hydrateDelivery($businessId, $deliveryId, $transaction);

        $trackingNumber = $hydrated['trackingNumber'] ?? null;
        $trackingUrl = $trackingNumber
            ? 'https://bosta.co/en-eg/tracking-shipments?shipment_number='.urlencode((string) $trackingNumber)
            : null;
        $status = $hydrated['state']['value'] ?? 'Created';

        StorefrontShipment::create([
            'business_id' => $businessId,
            'transaction_id' => $transaction->id,
            'carrier' => 'bosta',
            'external_id' => $deliveryId,
            'tracking_number' => $trackingNumber,
            'tracking_url' => $trackingUrl,
            'status' => is_string($status) ? $status : 'Created',
            'meta' => $hydrated ?: ['delivery_id' => $deliveryId],
        ]);

        return [
            'tracking_number' => $trackingNumber,
            'tracking_url' => $trackingUrl,
            'external_id' => $deliveryId,
            'label_url' => null,
            'meta' => $hydrated ?: ['delivery_id' => $deliveryId],
        ];
    }

    public function getTracking(int $businessId, string $externalId): ?array
    {
        if (! $this->isEnabled($businessId)) {
            return null;
        }

        // Prefer tracking number lookup (plugin path); fall back to delivery id search.
        $response = $this->api->request($businessId, 'GET', 'deliveries/business/'.rawurlencode($externalId));
        if (! $response['success']) {
            $search = $this->api->request($businessId, 'POST', 'deliveries/search', [
                'trackingNumbers' => $externalId,
            ]);
            if (! $search['success']) {
                return null;
            }
            $data = $search['body']['data']['deliveries'][0] ?? null;
        } else {
            $data = $response['body']['data'] ?? $response['body'];
        }

        if (! is_array($data)) {
            return null;
        }

        $trackingNumber = $data['trackingNumber'] ?? null;

        return [
            'status' => $data['state']['value'] ?? null,
            'tracking_number' => $trackingNumber,
            'tracking_url' => $trackingNumber
                ? 'https://bosta.co/en-eg/tracking-shipments?shipment_number='.urlencode((string) $trackingNumber)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildDeliveryPayload(int $businessId, Transaction $transaction): ?array
    {
        $address = $this->shippingAddressArray($transaction);
        $stateCode = strtoupper(trim((string) ($address['state'] ?? '')));
        $districtId = trim((string) ($address['district_id'] ?? $address['districtId'] ?? ''));
        $firstLine = trim((string) ($address['address_line_1'] ?? ''));
        if ($firstLine === '' && ! empty($transaction->shipping_address)) {
            $firstLine = trim((string) $transaction->shipping_address);
        }
        if (mb_strlen($firstLine) < 10) {
            Log::warning('Bosta createShipment: address firstLine too short', [
                'transaction_id' => $transaction->id,
            ]);

            return null;
        }
        if ($districtId === '') {
            Log::warning('Bosta createShipment: missing district_id', [
                'transaction_id' => $transaction->id,
            ]);

            return null;
        }

        $cityInfo = $this->api->cityByStateCode($businessId, $stateCode, 'en');
        $cityName = $cityInfo['city_name']
            ?? trim((string) ($address['city'] ?? ''))
            ?: 'Cairo';

        $contact = $transaction->contact;
        $firstName = trim((string) ($contact->first_name ?? ''));
        $lastName = trim((string) ($contact->last_name ?? ''));
        if ($firstName === '' && ! empty($contact->name)) {
            $parts = preg_split('/\s+/', trim((string) $contact->name), 2) ?: [];
            $firstName = $parts[0] ?? 'Customer';
            $lastName = $parts[1] ?? '';
        }
        if ($firstName === '') {
            $firstName = 'Customer';
        }

        $phone = $this->normalizePhone((string) ($contact->mobile ?? $contact->landline ?? ''));
        if ($phone === '') {
            Log::warning('Bosta createShipment: missing phone', ['transaction_id' => $transaction->id]);

            return null;
        }

        $itemsCount = 0;
        $descriptions = [];
        foreach ($transaction->sell_lines as $line) {
            $qty = (float) $line->quantity;
            $itemsCount += (int) max(1, round($qty));
            $name = $line->product->name ?? 'Item';
            $descriptions[] = $name.' x '.(int) $qty;
        }
        $description = implode(', ', $descriptions);
        if (mb_strlen($description) > 500) {
            $description = mb_substr($description, 0, 497).'...';
        }
        if ($description === '') {
            $description = (string) ($transaction->invoice_no ?: $transaction->storefront_order_id ?: $transaction->id);
        }

        $isCod = $this->isCodOrder($transaction);
        $cod = $isCod ? (float) $transaction->final_total : 0.0;

        $goodsAmount = 0.0;
        foreach ($transaction->sell_lines as $line) {
            $goodsAmount += (float) $line->unit_price_inc_tax * (float) $line->quantity;
        }

        return [
            'type' => 10,
            'notes' => $transaction->additional_notes ?: $transaction->sale_note,
            'uniqueBusinessReference' => 'SF_'.$transaction->id,
            'businessReference' => (string) ($transaction->storefront_order_id ?: $transaction->invoice_no ?: $transaction->id),
            'specs' => [
                'packageDetails' => [
                    'itemsCount' => max(1, $itemsCount),
                    'description' => $description,
                ],
            ],
            'receiver' => [
                'firstName' => mb_substr($firstName, 0, 50),
                'lastName' => $lastName,
                'phone' => $phone,
            ],
            'dropOffAddress' => [
                'firstLine' => $firstLine,
                'secondLine' => trim((string) ($address['address_line_2'] ?? '')) ?: null,
                'city' => $cityName,
                'districtId' => $districtId,
            ],
            'cod' => $cod,
            'goodsInfo' => [
                'amount' => round($goodsAmount > 0 ? $goodsAmount : (float) $transaction->final_total, 2),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function hydrateDelivery(int $businessId, string $deliveryId, Transaction $transaction): array
    {
        $response = $this->api->request($businessId, 'POST', 'deliveries/woocommerce-data', [
            'deliveriesIds' => [$deliveryId],
        ]);

        if ($response['success']) {
            $list = $response['body']['data'] ?? [];
            if (is_array($list)) {
                foreach ($list as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $ref = (string) ($row['uniqueBusinessReference'] ?? '');
                    if ($ref === 'SF_'.$transaction->id || ($row['_id'] ?? null) === $deliveryId) {
                        return $row;
                    }
                }
                if (isset($list[0]) && is_array($list[0])) {
                    return $list[0];
                }
            }
        }

        return ['_id' => $deliveryId];
    }

    /**
     * @return array<string, mixed>
     */
    private function shippingAddressArray(Transaction $transaction): array
    {
        $addresses = ! empty($transaction->order_addresses)
            ? json_decode($transaction->order_addresses, true)
            : [];
        $raw = is_array($addresses) ? ($addresses['shipping_address'] ?? []) : [];

        return is_array($raw) ? $raw : [];
    }

    private function isCodOrder(Transaction $transaction): bool
    {
        if ($transaction->relationLoaded('payment_lines')) {
            return $transaction->payment_lines->isEmpty();
        }

        return $transaction->payment_lines()->count() === 0;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0') && ! str_starts_with($digits, '20')) {
            $digits = '20'.substr($digits, 1);
        }
        if (! str_starts_with($digits, '20') && strlen($digits) === 10) {
            $digits = '20'.$digits;
        }

        return $digits;
    }
}
