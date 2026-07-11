<?php

namespace App\Services\Storefront\Shipping\Carriers;

use App\Services\Storefront\StorefrontSettingService;
use App\StorefrontShipment;
use App\Transaction;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bosta Egypt courier adapter (create shipment + tracking).
 */
class BostaShippingCarrier implements ShippingCarrierInterface
{
    public function __construct(private StorefrontSettingService $settings)
    {
    }

    public function key(): string
    {
        return 'bosta';
    }

    public function isEnabled(int $businessId): bool
    {
        $cfg = $this->settings->get($businessId);

        return ! empty($cfg['couriers']['bosta']['enabled'])
            && ! empty($cfg['couriers']['bosta']['api_key']);
    }

    public function createShipment(int $businessId, Transaction $transaction): ?array
    {
        if (! $this->isEnabled($businessId)) {
            return null;
        }

        $cfg = $this->settings->get($businessId);
        $apiKey = $this->decryptKey($cfg['couriers']['bosta']['api_key'] ?? null);
        if (! $apiKey) {
            return null;
        }

        $staging = ! empty($cfg['couriers']['bosta']['staging']);
        $base = $staging
            ? 'https://stg-app.bosta.co/api/v2'
            : 'https://app.bosta.co/api/v2';

        $payload = [
            'type' => 10,
            'specs' => [
                'packageDetails' => [
                    'itemsCount' => max(1, (int) $transaction->sell_lines()->count()),
                    'description' => $transaction->invoice_no ?: $transaction->storefront_order_id,
                ],
            ],
            'notes' => $transaction->additional_notes,
            'businessReference' => (string) ($transaction->storefront_order_id ?: $transaction->id),
            'receiver' => [
                'firstName' => $transaction->contact->first_name ?? $transaction->contact->name ?? 'Customer',
                'lastName' => $transaction->contact->last_name ?? '',
                'phone' => $transaction->contact->mobile ?? '',
            ],
            'dropOffAddress' => [
                'firstLine' => $transaction->shipping_address ?: 'Address on file',
                'city' => 'Cairo',
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(20)->post($base.'/deliveries', $payload);

            if (! $response->successful()) {
                Log::warning('Bosta createShipment failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json('data') ?? $response->json();
            $trackingNumber = $data['trackingNumber'] ?? $data['tracking_number'] ?? null;
            $externalId = $data['_id'] ?? $data['id'] ?? null;
            $trackingUrl = $trackingNumber
                ? 'https://bosta.co/en-eg/tracking-shipments?shipment_number='.urlencode((string) $trackingNumber)
                : null;

            StorefrontShipment::create([
                'business_id' => $businessId,
                'transaction_id' => $transaction->id,
                'carrier' => 'bosta',
                'external_id' => $externalId,
                'tracking_number' => $trackingNumber,
                'tracking_url' => $trackingUrl,
                'status' => $data['state']['value'] ?? 'created',
                'meta' => $data,
            ]);

            return [
                'tracking_number' => $trackingNumber,
                'tracking_url' => $trackingUrl,
                'external_id' => $externalId ? (string) $externalId : null,
                'label_url' => null,
                'meta' => is_array($data) ? $data : [],
            ];
        } catch (\Throwable $e) {
            Log::warning('Bosta createShipment exception: '.$e->getMessage());

            return null;
        }
    }

    public function getTracking(int $businessId, string $externalId): ?array
    {
        if (! $this->isEnabled($businessId)) {
            return null;
        }

        $cfg = $this->settings->get($businessId);
        $apiKey = $this->decryptKey($cfg['couriers']['bosta']['api_key'] ?? null);
        if (! $apiKey) {
            return null;
        }

        $staging = ! empty($cfg['couriers']['bosta']['staging']);
        $base = $staging
            ? 'https://stg-app.bosta.co/api/v2'
            : 'https://app.bosta.co/api/v2';

        try {
            $response = Http::withHeaders([
                'Authorization' => $apiKey,
            ])->timeout(15)->get($base.'/deliveries/business-reference/'.$externalId);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json('data') ?? $response->json();

            return [
                'status' => $data['state']['value'] ?? null,
                'tracking_number' => $data['trackingNumber'] ?? null,
                'tracking_url' => null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function decryptKey(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }
}
