<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontSellRequestMedia extends Model
{
    protected $table = 'storefront_sell_request_media';

    protected $guarded = ['id'];

    public function sellRequest(): BelongsTo
    {
        return $this->belongsTo(StorefrontSellRequest::class, 'sell_request_id');
    }

    public function getUrlAttribute(): string
    {
        $path = ltrim((string) $this->path, '/');

        return asset('uploads/'.$path);
    }
}
