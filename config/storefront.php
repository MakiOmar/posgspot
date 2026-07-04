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

    /** POST/PUT/PATCH/DELETE budget per IP (auth, checkout, contact, etc.). */
    'rate_limit_per_minute' => (int) env('STOREFRONT_RATE_LIMIT', 120),

    /**
     * GET/HEAD budget per IP. Qwik SSR re-fetches settings/categories on each
     * navigation from a single server IP, so reads need a higher ceiling.
     */
    'rate_limit_read_per_minute' => (int) env('STOREFRONT_RATE_LIMIT_READ', 600),

    /*
    | Public Qwik storefront origin — used in password-reset emails and similar links.
    | Example: https://shop.example.com (no trailing slash).
    */
    'url' => rtrim((string) env('STOREFRONT_URL', env('APP_URL', 'http://localhost:5173')), '/'),

];
