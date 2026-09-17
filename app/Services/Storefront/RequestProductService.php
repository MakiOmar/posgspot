<?php

namespace App\Services\Storefront;

use App\Contact;
use App\Mail\StorefrontProductRequestSubmitted;
use App\StorefrontProductRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Request a product: public submissions + staff notification.
 */
class RequestProductService
{
    public function __construct(private StorefrontMailService $mailService)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) config('storefront.request_product.enabled', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [
            'enabled' => true,
            'platforms' => [
                ['id' => 'ps5', 'label' => 'PS5'],
                ['id' => 'ps4', 'label' => 'PS4'],
                ['id' => 'switch', 'label' => 'Nintendo Switch'],
                ['id' => 'xbox', 'label' => 'Xbox'],
                ['id' => 'pc', 'label' => 'PC'],
                ['id' => 'other', 'label' => 'Other'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRequest(int $businessId, ?Contact $contact, array $data): StorefrontProductRequest
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($contact !== null && $name === '') {
            $name = trim((string) ($contact->name ?? ''));
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($contact !== null && $email === '') {
            $email = trim((string) ($contact->email ?? ''));
        }

        $phone = trim((string) ($data['phone'] ?? ''));
        if ($contact !== null && $phone === '') {
            $phone = trim((string) ($contact->mobile ?? ''));
        }

        $row = StorefrontProductRequest::create([
            'business_id' => $businessId,
            'contact_id' => $contact?->id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'product_name' => trim((string) ($data['product_name'] ?? '')),
            'platform' => $this->optionalPlatform($data['platform'] ?? null),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'status' => StorefrontProductRequest::STATUS_NEW,
        ]);

        $this->queueNotifyMail($businessId, $row);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(StorefrontProductRequest $row): array
    {
        return [
            'id' => $row->id,
            'status' => $row->status,
            'name' => $row->name,
            'email' => $row->email,
            'phone' => $row->phone,
            'product_name' => $row->product_name,
            'platform' => $row->platform,
            'notes' => $row->notes,
            'created_at' => optional($row->created_at)?->toIso8601String(),
        ];
    }

    private function optionalPlatform(mixed $value): ?string
    {
        $value = is_string($value) ? trim(strtolower($value)) : '';
        $allowed = ['ps5', 'ps4', 'switch', 'xbox', 'pc', 'other'];

        return in_array($value, $allowed, true) ? $value : null;
    }

    private function queueNotifyMail(int $businessId, StorefrontProductRequest $request): void
    {
        try {
            $this->mailService->applyForBusiness($businessId);
            $recipient = $this->mailService->requestProductRecipient($businessId);
            Mail::to($recipient)->queue(new StorefrontProductRequestSubmitted($request));
            Log::info('Storefront request-product email queued.', [
                'business_id' => $businessId,
                'product_request_id' => $request->id,
                'recipient' => $recipient,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Storefront request-product email failed.', [
                'business_id' => $businessId,
                'product_request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }
    }
}
