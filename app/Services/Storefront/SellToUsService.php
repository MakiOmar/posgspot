<?php

namespace App\Services\Storefront;

use App\Contact;
use App\Mail\StorefrontSellRequestSubmitted;
use App\StorefrontSellRequest;
use App\StorefrontSellRequestMedia;
use App\Utils\Util;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sell to us / trade-in: invoice verify + request create + photo storage.
 */
class SellToUsService
{
    public function __construct(
        private CheckoutService $checkout,
        private StorefrontMailService $mailService,
        private Util $util
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('storefront.sell_to_us.enabled', false);
    }

    public function maxPhotos(): int
    {
        return max(1, (int) config('storefront.sell_to_us.max_photos', 6));
    }

    public function maxPhotoKb(): int
    {
        return max(100, (int) config('storefront.sell_to_us.max_photo_kb', 4096));
    }

    /**
     * Public form options for web + mobile clients.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [
            'enabled' => true,
            'types' => [
                [
                    'id' => StorefrontSellRequest::TYPE_ACCOUNT,
                    'label' => 'Game account',
                ],
                [
                    'id' => StorefrontSellRequest::TYPE_DISC,
                    'label' => 'CD / disc',
                ],
                [
                    'id' => StorefrontSellRequest::TYPE_DEVICE,
                    'label' => 'Device',
                ],
            ],
            'cities' => $this->cities(),
            'platforms' => [
                ['id' => 'ps5', 'label' => 'PS5'],
                ['id' => 'ps4', 'label' => 'PS4'],
            ],
            'device_models' => [
                ['id' => 'ps5_slim', 'label' => 'PS5 Slim'],
                ['id' => 'ps5_fat', 'label' => 'PS5 Fat'],
                ['id' => 'ps5_pro', 'label' => 'PS5 Pro'],
                ['id' => 'ps4_slim', 'label' => 'PS4 Slim'],
                ['id' => 'ps4_pro', 'label' => 'PS4 Pro'],
                ['id' => 'ps4_fat', 'label' => 'PS4 Fat'],
                ['id' => 'other', 'label' => 'Other'],
            ],
            'storage_options' => [
                ['id' => '825gb', 'label' => '825 GB'],
                ['id' => '1tb', 'label' => '1 TB'],
                ['id' => '2tb', 'label' => '2 TB'],
                ['id' => 'other', 'label' => 'Other'],
            ],
            'conditions' => [
                ['id' => 'new', 'label' => 'Like new'],
                ['id' => 'excellent', 'label' => 'Excellent'],
                ['id' => 'good', 'label' => 'Good'],
                ['id' => 'fair', 'label' => 'Fair'],
                ['id' => 'for_parts', 'label' => 'For parts'],
            ],
            'purchased_from_us' => [
                'invoice_required_when_yes' => true,
                'verify_required_when_yes' => true,
            ],
            'max_photos' => $this->maxPhotos(),
            'max_photo_kb' => $this->maxPhotoKb(),
        ];
    }

    /**
     * @return array{valid: bool, order: array<string, mixed>|null}
     */
    public function verifyInvoice(int $businessId, Contact $contact, string $invoiceNo): array
    {
        $invoiceNo = trim($invoiceNo);
        if ($invoiceNo === '') {
            throw ValidationException::withMessages([
                'invoice_no' => ['Invoice number is required.'],
            ]);
        }

        $order = $this->checkout->getOrderForContact($businessId, (int) $contact->id, $invoiceNo);
        if ($order === null) {
            throw ValidationException::withMessages([
                'invoice_no' => ['We could not find this invoice on your account.'],
            ]);
        }

        return [
            'valid' => true,
            'order' => [
                'id' => (int) ($order['id'] ?? 0),
                'invoice_no' => $order['invoice_no'] ?? null,
                'storefront_order_id' => $order['storefront_order_id'] ?? null,
                'final_total' => isset($order['final_total']) ? (float) $order['final_total'] : null,
                'created_at' => $order['created_at'] ?? ($order['transaction_date'] ?? null),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $photos
     */
    public function createRequest(int $businessId, Contact $contact, array $data, array $photos = []): StorefrontSellRequest
    {
        $type = (string) ($data['type'] ?? '');
        if (! in_array($type, StorefrontSellRequest::TYPES, true)) {
            throw ValidationException::withMessages([
                'type' => ['Invalid sell request type.'],
            ]);
        }

        $purchasedFromUs = (bool) ($data['purchased_from_us'] ?? false);
        $invoiceNo = trim((string) ($data['invoice_no'] ?? ''));
        $transactionId = isset($data['transaction_id']) ? (int) $data['transaction_id'] : null;

        if ($purchasedFromUs) {
            if ($invoiceNo === '') {
                throw ValidationException::withMessages([
                    'invoice_no' => ['Invoice number is required when the item was purchased from us.'],
                ]);
            }
            $verified = $this->verifyInvoice($businessId, $contact, $invoiceNo);
            $orderId = (int) ($verified['order']['id'] ?? 0);
            if ($transactionId && $transactionId !== $orderId) {
                throw ValidationException::withMessages([
                    'transaction_id' => ['Verified order does not match the submitted transaction.'],
                ]);
            }
            $transactionId = $orderId;
            $invoiceNo = (string) ($verified['order']['invoice_no'] ?? $invoiceNo);
        } else {
            $invoiceNo = $invoiceNo !== '' ? $invoiceNo : null;
            $transactionId = null;
        }

        $details = $this->normalizeDetails($type, $data['details'] ?? []);

        if ($type === StorefrontSellRequest::TYPE_DISC && ($details['game_title'] ?? '') === '') {
            throw ValidationException::withMessages([
                'details.game_title' => ['Game title is required for disc trade-ins.'],
            ]);
        }
        if ($type === StorefrontSellRequest::TYPE_DEVICE) {
            if (($details['model'] ?? '') === '') {
                throw ValidationException::withMessages([
                    'details.model' => ['Device model is required.'],
                ]);
            }
            if (($details['condition'] ?? '') === '') {
                throw ValidationException::withMessages([
                    'details.condition' => ['Device condition is required.'],
                ]);
            }
        }

        if ($type === StorefrontSellRequest::TYPE_DEVICE && count($photos) > $this->maxPhotos()) {
            throw ValidationException::withMessages([
                'photos' => ['You may upload at most '.$this->maxPhotos().' photos.'],
            ]);
        }

        $maxBytes = $this->maxPhotoKb() * 1024;
        foreach ($photos as $i => $photo) {
            if (! $photo instanceof UploadedFile || ! $photo->isValid()) {
                throw ValidationException::withMessages([
                    'photos.'.$i => ['Invalid photo upload.'],
                ]);
            }
            $mime = (string) $photo->getMimeType();
            if (! str_starts_with($mime, 'image/')) {
                throw ValidationException::withMessages([
                    'photos.'.$i => ['Photos must be image files.'],
                ]);
            }
            if ((int) $photo->getSize() > $maxBytes) {
                throw ValidationException::withMessages([
                    'photos.'.$i => ['Each photo must be under '.$this->maxPhotoKb().' KB.'],
                ]);
            }
        }

        $request = DB::transaction(function () use (
            $businessId,
            $contact,
            $type,
            $data,
            $purchasedFromUs,
            $invoiceNo,
            $transactionId,
            $details,
            $photos
        ) {
            $row = StorefrontSellRequest::create([
                'business_id' => $businessId,
                'contact_id' => $contact->id,
                'type' => $type,
                'name' => trim((string) ($data['name'] ?? $contact->name ?? '')),
                'phone' => trim((string) ($data['phone'] ?? $contact->mobile ?? '')) ?: null,
                'email' => trim((string) ($data['email'] ?? $contact->email ?? '')) ?: null,
                'city' => trim((string) ($data['city'] ?? '')) ?: null,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'purchased_from_us' => $purchasedFromUs,
                'invoice_no' => $invoiceNo,
                'transaction_id' => $transactionId,
                'details' => $details,
                'status' => StorefrontSellRequest::STATUS_NEW,
            ]);

            $dir = 'storefront_sell/'.$businessId;
            foreach (array_values($photos) as $index => $photo) {
                $ext = strtolower((string) $photo->getClientOriginalExtension()) ?: 'jpg';
                $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'jpg';
                $fileName = Str::uuid()->toString().'.'.$ext;
                $stored = $photo->storeAs($dir, $fileName);
                if (! is_string($stored) || $stored === '') {
                    throw ValidationException::withMessages([
                        'photos' => ['Could not store uploaded photo.'],
                    ]);
                }
                $this->util->ensurePublicUploadPermissions($dir, $fileName);
                StorefrontSellRequestMedia::create([
                    'sell_request_id' => $row->id,
                    'path' => str_replace('\\', '/', $stored),
                    'sort_order' => $index,
                ]);
            }

            return $row->fresh(['media']);
        });

        $this->queueNotifyMail($businessId, $request);

        return $request;
    }

    /**
     * @param  array<string, mixed>|string|null  $raw
     * @return array<string, mixed>
     */
    private function normalizeDetails(string $type, $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            $raw = [];
        }

        return match ($type) {
            StorefrontSellRequest::TYPE_ACCOUNT => [
                'account_note' => trim((string) ($raw['account_note'] ?? '')),
            ],
            StorefrontSellRequest::TYPE_DISC => [
                'game_title' => trim((string) ($raw['game_title'] ?? '')),
                'platform' => $this->optionalIn($raw['platform'] ?? null, ['ps4', 'ps5']),
                'condition' => $this->optionalIn($raw['condition'] ?? null, [
                    'new', 'excellent', 'good', 'fair', 'for_parts',
                ]),
                'product_id' => isset($raw['product_id']) && is_numeric($raw['product_id'])
                    ? (int) $raw['product_id']
                    : null,
            ],
            StorefrontSellRequest::TYPE_DEVICE => [
                'model' => trim((string) ($raw['model'] ?? '')),
                'storage' => trim((string) ($raw['storage'] ?? '')),
                'condition' => $this->optionalIn($raw['condition'] ?? null, [
                    'new', 'excellent', 'good', 'fair', 'for_parts',
                ]),
            ],
            default => [],
        };
    }

    /**
     * @param  list<string>  $allowed
     */
    private function optionalIn(mixed $value, array $allowed): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || ! in_array($value, $allowed, true)) {
            return null;
        }

        return $value;
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function cities(): array
    {
        $names = [
            'Cairo', 'Giza', 'Alexandria', 'Port Said', 'Suez', 'Luxor', 'Aswan',
            'Mansoura', 'Tanta', 'Zagazig', 'Ismailia', 'Fayoum', 'Asyut', 'Minya',
            'Sohag', 'Qena', 'Hurghada', 'Sharm El Sheikh', 'Damietta', 'Beni Suef',
            'Other',
        ];

        return array_map(fn (string $name) => [
            'id' => Str::slug($name, '_'),
            'label' => $name,
        ], $names);
    }

    private function queueNotifyMail(int $businessId, StorefrontSellRequest $request): void
    {
        try {
            $this->mailService->applyForBusiness($businessId);
            $recipient = $this->mailService->sellToUsRecipient($businessId);
            Mail::to($recipient)->queue(new StorefrontSellRequestSubmitted($request));
            Log::info('Storefront sell-to-us email queued.', [
                'business_id' => $businessId,
                'sell_request_id' => $request->id,
                'recipient' => $recipient,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Storefront sell-to-us email failed.', [
                'business_id' => $businessId,
                'sell_request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }
    }

    /**
     * Present a request for API / admin consumers.
     *
     * @return array<string, mixed>
     */
    public function present(StorefrontSellRequest $row): array
    {
        if (! $row->relationLoaded('media')) {
            $row->load('media');
        }

        return [
            'id' => $row->id,
            'type' => $row->type,
            'status' => $row->status,
            'name' => $row->name,
            'phone' => $row->phone,
            'email' => $row->email,
            'city' => $row->city,
            'notes' => $row->notes,
            'purchased_from_us' => (bool) $row->purchased_from_us,
            'invoice_no' => $row->invoice_no,
            'transaction_id' => $row->transaction_id,
            'details' => $row->details ?? [],
            'photos' => $row->media->map(fn (StorefrontSellRequestMedia $m) => [
                'id' => $m->id,
                'url' => $m->url,
                'sort_order' => (int) $m->sort_order,
            ])->values()->all(),
            'created_at' => optional($row->created_at)?->toIso8601String(),
        ];
    }
}
