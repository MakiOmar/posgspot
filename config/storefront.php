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

    /** POST auth endpoints (register, login, forgot/reset password) per IP. */
    'auth_rate_limit_per_minute' => (int) env('STOREFRONT_AUTH_RATE_LIMIT', 20),

    /** Customer password-reset token lifetime (minutes). */
    'password_reset_expire_minutes' => (int) env('STOREFRONT_PASSWORD_RESET_EXPIRE_MINUTES', 60),

    /**
     * Storefront Sanctum bearer token lifetime (minutes). Default 30 days.
     * Set to 0 to disable expiration (not recommended for production).
     */
    'sanctum_expiration_minutes' => (int) env('STOREFRONT_SANCTUM_EXPIRATION_MINUTES', 43200),

    /** Max saved wishlist rows per customer. */
    'wishlist_max_items' => (int) env('STOREFRONT_WISHLIST_MAX_ITEMS', 100),

    /** Max product IDs accepted in one wishlist merge request. */
    'wishlist_merge_max_ids' => (int) env('STOREFRONT_WISHLIST_MERGE_MAX_IDS', 100),

    /*
    | Public Qwik storefront origin — used in password-reset emails and similar links.
    | Example: https://shop.example.com (no trailing slash).
    */
    'url' => rtrim((string) env('STOREFRONT_URL', env('APP_URL', 'http://localhost:5173')), '/'),

    /**
     * Extra digital pricing diagnostics (checkout API `_price_debug`, sell-details panel).
     * Logs for digital checkouts always write to laravel.log; set true to also force the UI panel.
     */
    'price_debug' => filter_var(env('STOREFRONT_PRICE_DEBUG', false), FILTER_VALIDATE_BOOLEAN),

];
