<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storefront static defaults
    |--------------------------------------------------------------------------
    |
    | Operational settings (selling locations, COD, shipping, etc.) are stored
    | in the storefront_settings table and managed via the admin settings page.
    | Only static deployment defaults live here.
    |
    */

    'business_id' => (int) env('STOREFRONT_BUSINESS_ID', 1),

    'rate_limit_per_minute' => (int) env('STOREFRONT_RATE_LIMIT', 120),

];
