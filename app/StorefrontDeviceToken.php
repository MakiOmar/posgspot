<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontDeviceToken extends Model
{
    protected $table = 'storefront_device_tokens';

    protected $fillable = [
        'business_id',
        'contact_id',
        'platform',
        'token',
        'locale',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
