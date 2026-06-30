<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-business storefront configuration (JSON value blob).
 */
class StorefrontSetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'value' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
