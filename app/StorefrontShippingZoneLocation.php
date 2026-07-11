<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StorefrontShippingZoneLocation extends Model
{
    protected $table = 'storefront_shipping_zone_locations';

    protected $guarded = ['id'];

    public function zone()
    {
        return $this->belongsTo(StorefrontShippingZone::class, 'zone_id');
    }
}
