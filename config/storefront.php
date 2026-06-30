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

    /*
    | Public Qwik storefront origin — used in password-reset emails and similar links.
    | Example: https://shop.example.com (no trailing slash).
    */
    'url' => rtrim((string) env('STOREFRONT_URL', env('APP_URL', 'http://localhost:5173')), '/'),

];
