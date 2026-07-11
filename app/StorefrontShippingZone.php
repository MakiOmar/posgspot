<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StorefrontShippingZone extends Model
{
    protected $table = 'storefront_shipping_zones';

    protected $guarded = ['id'];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_catch_all' => 'boolean',
        'priority' => 'integer',
    ];

    public function locations()
    {
        return $this->hasMany(StorefrontShippingZoneLocation::class, 'zone_id');
    }

    public function methods()
    {
        return $this->hasMany(StorefrontShippingMethod::class, 'zone_id');
    }
}
