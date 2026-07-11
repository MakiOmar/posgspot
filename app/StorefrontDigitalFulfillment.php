<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class StorefrontDigitalFulfillment extends Model
{
    protected $table = 'storefront_digital_fulfillments';

    protected $guarded = ['id'];

    protected $casts = [
        'request_meta' => 'array',
        'allocated_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function sellLine()
    {
        return $this->belongsTo(TransactionSellLine::class, 'sell_line_id');
    }

    public function storeSecrets(?array $secrets): void
    {
        $this->secret_payload = $secrets === null
            ? null
            : Crypt::encryptString(json_encode($secrets));
    }

    public function readSecrets(): ?array
    {
        $raw = $this->attributes['secret_payload'] ?? null;
        if (empty($raw)) {
            return null;
        }
        try {
            $decoded = json_decode(Crypt::decryptString($raw), true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Public customer-safe delivery payload. */
    public function toCustomerDelivery(): ?array
    {
        if ($this->status !== 'allocated') {
            return null;
        }
        $secrets = $this->readSecrets();
        if (! is_array($secrets)) {
            return null;
        }

        return [
            'kind' => $this->kind,
            'line_key' => $this->line_key,
            'title' => $this->request_meta['title'] ?? null,
            'account_email' => $secrets['email'] ?? null,
            'account_password' => $secrets['password'] ?? null,
            'code' => $secrets['code'] ?? null,
            'allocated_at' => optional($this->allocated_at)->toIso8601String(),
        ];
    }
}
