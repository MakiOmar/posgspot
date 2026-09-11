<?php

namespace App\Services\Storefront;

use App\Contact;
use App\Services\Storefront\Accounts\AccountsApiClient;

/**
 * Proxies public console/device service lookups to the Accounts Device Track API.
 */
class DeviceTrackLookupService
{
    public function __construct(private AccountsApiClient $accounts)
    {
    }

    public function isConfigured(): bool
    {
        return $this->accounts->baseUrl() !== '';
    }

    /**
     * @return array{ok:bool,status:int,message:string,services:list<array<string,mixed>>,errors:array<string,list<string>>}
     */
    public function trackByPhone(string $phone): array
    {
        $phone = trim($phone);
        if ($phone === '') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'The phone number field is required.',
                'services' => [],
                'errors' => ['phone_number' => ['The phone number field is required.']],
            ];
        }

        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'status' => 503,
                'message' => 'Device tracking is not available.',
                'services' => [],
                'errors' => [],
            ];
        }

        $result = $this->accounts->trackDevices($phone);
        $status = (int) ($result['status'] ?? 0);
        $body = is_array($result['body'] ?? null) ? $result['body'] : [];

        if ($status === 0) {
            return [
                'ok' => false,
                'status' => 503,
                'message' => $result['error'] ?: 'Device tracking is not available.',
                'services' => [],
                'errors' => [],
            ];
        }

        if ($status === 422) {
            $errors = [];
            if (isset($body['errors']) && is_array($body['errors'])) {
                foreach ($body['errors'] as $field => $messages) {
                    $errors[(string) $field] = array_values(array_map('strval', (array) $messages));
                }
            }

            return [
                'ok' => false,
                'status' => 422,
                'message' => (string) ($body['message'] ?? 'Invalid phone number.'),
                'services' => [],
                'errors' => $errors,
            ];
        }

        if ($status === 429) {
            return [
                'ok' => false,
                'status' => 429,
                'message' => (string) ($body['message'] ?? 'Too many requests. Please try again shortly.'),
                'services' => [],
                'errors' => [],
            ];
        }

        $raw = $body['data'] ?? [];
        $services = [];
        if (is_array($raw)) {
            foreach ($raw as $row) {
                if (is_array($row)) {
                    $services[] = $this->normalizeService($row);
                }
            }
        }

        if ($status === 404 || ($result['success'] === false && $services === [])) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => (string) ($body['message'] ?? 'No services found for this phone number.'),
                'services' => [],
                'errors' => [],
            ];
        }

        if ($status >= 200 && $status < 300) {
            return [
                'ok' => true,
                'status' => 200,
                'message' => (string) ($body['message'] ?? 'Services found.'),
                'services' => $services,
                'errors' => [],
            ];
        }

        return [
            'ok' => false,
            'status' => $status >= 400 ? $status : 502,
            'message' => (string) ($body['message'] ?? $result['error'] ?? 'Device tracking failed.'),
            'services' => [],
            'errors' => [],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function forContact(Contact $contact): array
    {
        $phone = trim((string) ($contact->mobile ?? ''));
        if ($phone === '') {
            return [];
        }

        $result = $this->trackByPhone($phone);

        return $result['services'];
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function normalizeService(array $row): array
    {
        $model = is_array($row['device_model'] ?? null) ? $row['device_model'] : null;
        $store = is_array($row['store_profile'] ?? null) ? $row['store_profile'] : null;

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'tracking_code' => (string) ($row['tracking_code'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'status_display' => (string) ($row['status_display'] ?? ($row['status'] ?? '')),
            'device_serial_number' => (string) ($row['device_serial_number'] ?? ''),
            'notes' => isset($row['notes']) && $row['notes'] !== null ? (string) $row['notes'] : null,
            'submitted_at' => isset($row['submitted_at']) && $row['submitted_at'] !== null
                ? (string) $row['submitted_at']
                : null,
            'status_updated_at' => isset($row['status_updated_at']) && $row['status_updated_at'] !== null
                ? (string) $row['status_updated_at']
                : null,
            'client_name' => (string) ($row['client_name'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'device_model' => $model ? [
                'id' => isset($model['id']) ? (int) $model['id'] : null,
                'name' => (string) ($model['name'] ?? ''),
                'brand' => (string) ($model['brand'] ?? ''),
                'full_name' => (string) ($model['full_name'] ?? ''),
            ] : null,
            'store_profile' => $store ? [
                'id' => isset($store['id']) ? (int) $store['id'] : null,
                'name' => (string) ($store['name'] ?? ''),
            ] : null,
        ];
    }
}
