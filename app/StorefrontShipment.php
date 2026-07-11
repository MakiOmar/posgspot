<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StorefrontShipment extends Model
{
    protected $table = 'storefront_shipments';

    protected $guarded = ['id'];

    protected $casts = [
        'meta' => 'array',
    ];
}
