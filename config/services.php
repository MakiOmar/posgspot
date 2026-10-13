<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'woo' => [
        'endpoint' => env('WOO_ENDPOINT'),
        'token'    => env('WOO_TOKEN'),
    ],
    'accounts' => [
        'base' => env('ACCOUNTS_BASE_URL'),
        'phone' => env('ACCOUNTS_AUTH_PHONE'),
        'password' => env('ACCOUNTS_AUTH_PASSWORD'),
        // Existing POS category / brand that per-offer products synced from Accounts are filed under.
        'catalog_category_slug' => env('ACCOUNTS_CATALOG_CATEGORY_SLUG', 'digital-games'),
        'catalog_card_category_slug' => env('ACCOUNTS_CATALOG_CARD_CATEGORY_SLUG'),
        'catalog_brand_slug' => env('ACCOUNTS_CATALOG_BRAND_SLUG', 'games-spot'),
        // Per API user; replaces the default 60/min api limit on the catalog upsert route (Accounts backfill).
        'catalog_rate_limit_per_minute' => (int) env('ACCOUNTS_CATALOG_RATE_LIMIT_PER_MINUTE', 300),
        // Call-center sales orders: allocate Accounts game accounts / cards from POS automatically.
        'sales_order_fulfillment' => filter_var(env('ACCOUNTS_SALES_ORDER_FULFILLMENT', true), FILTER_VALIDATE_BOOLEAN),
        // Also sell synced Accounts products on POS / direct sells (they stay not_for_selling, off the storefront).
        'pos_sell' => filter_var(env('ACCOUNTS_POS_SELL', true), FILTER_VALIDATE_BOOLEAN),
        // Accounts store profile credited for sales-order allocations; empty = storefront digital setting.
        'sales_order_store_profile_id' => env('ACCOUNTS_SALES_ORDER_STORE_PROFILE_ID') !== null && env('ACCOUNTS_SALES_ORDER_STORE_PROFILE_ID') !== ''
            ? (int) env('ACCOUNTS_SALES_ORDER_STORE_PROFILE_ID') : null,
        // Pre-sync placeholder products Accounts used for "Send to POS" (classify old sales as accounts).
        // No default: plain numeric SKUs collide with physical products (e.g. 0143 is a real item on live).
        'legacy_pos_skus' => array_values(array_filter(array_map('trim', explode(',', (string) env('ACCOUNTS_LEGACY_POS_SKUS', ''))))),
        'legacy_product_ids' => array_values(array_filter(array_map('intval', explode(',', (string) env('ACCOUNTS_LEGACY_PRODUCT_IDS', ''))))),
    ],

    /*
    | Storefront social login (Laravel Socialite). Redirect URI must match the
    | API callback: {APP_URL}/api/storefront/v1/auth/social/{provider}/callback
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/api/storefront/v1/auth/social/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/api/storefront/v1/auth/social/facebook/callback'),
    ],

];
