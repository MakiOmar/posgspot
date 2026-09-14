<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontSavedCoupon extends Model
{
    protected $table = 'storefront_saved_coupons';

    protected $fillable = [
        'business_id',
        'contact_id',
        'coupon_id',
        'code',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
