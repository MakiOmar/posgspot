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

    /*
    | FCM HTTP v1 for storefront mobile push (optional).
    | Set STOREFRONT_FCM_PROJECT_ID and path to a Google service-account JSON.
    */
    'fcm_project_id' => (string) env('STOREFRONT_FCM_PROJECT_ID', ''),
    'fcm_credentials_path' => (string) env('STOREFRONT_FCM_CREDENTIALS_PATH', ''),

    /*
    | Social login (Google / Facebook via Socialite). Enabled only when the
    | matching client id + secret are set (or explicitly forced via env).
    | Public clients see social_login.* flags — never secrets.
    */
    'social_login' => [
        'google' => [
            'enabled' => filter_var(
                env('STOREFRONT_SOCIAL_GOOGLE', null),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? (filled(env('GOOGLE_CLIENT_ID')) && filled(env('GOOGLE_CLIENT_SECRET'))),
        ],
        'facebook' => [
            'enabled' => filter_var(
                env('STOREFRONT_SOCIAL_FACEBOOK', null),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? (filled(env('FACEBOOK_CLIENT_ID')) && filled(env('FACEBOOK_CLIENT_SECRET'))),
        ],
        /** One-time web exchange code TTL (seconds). */
        'exchange_ttl_seconds' => (int) env('STOREFRONT_SOCIAL_EXCHANGE_TTL', 60),
        /** OAuth state payload TTL (seconds). */
        'state_ttl_seconds' => (int) env('STOREFRONT_SOCIAL_STATE_TTL', 600),
    ],

    /*
    | AI support chat (OpenAI). Enabled only when STOREFRONT_SUPPORT_CHAT=true
    | and OPENAI_API_KEY is set. Escalation into CRM requires employee/location/user ids.
    */
    'support_chat' => [
        'enabled' => filter_var(env('STOREFRONT_SUPPORT_CHAT', false), FILTER_VALIDATE_BOOLEAN),
        'model' => (string) env('STOREFRONT_SUPPORT_CHAT_MODEL', 'gpt-4o-mini'),
        'rate_limit_per_minute' => (int) env('STOREFRONT_SUPPORT_CHAT_RATE_LIMIT', 30),
        'escalation_employee_id' => (int) env('STOREFRONT_SUPPORT_ESCALATION_EMPLOYEE_ID', 0),
        'escalation_location_id' => (int) env('STOREFRONT_SUPPORT_ESCALATION_LOCATION_ID', 0),
        'escalation_created_by' => (int) env('STOREFRONT_SUPPORT_ESCALATION_CREATED_BY', 0),
        'escalation_source_name' => (string) env('STOREFRONT_SUPPORT_ESCALATION_SOURCE_NAME', 'Storefront AI Chat'),
    ],

    /*
    | Custom Bundle builder (physical catalog only). Web + mobile share
    | GET /custom-bundle/* when enabled.
    */
    'custom_bundle' => [
        'enabled' => filter_var(env('STOREFRONT_CUSTOM_BUNDLE', false), FILTER_VALIDATE_BOOLEAN),
        'min_items' => max(1, (int) env('STOREFRONT_CUSTOM_BUNDLE_MIN_ITEMS', 2)),
        'max_items' => max(1, (int) env('STOREFRONT_CUSTOM_BUNDLE_MAX_ITEMS', 15)),
    ],

    /*
    | Sell to us / trade-in. Enabled via STOREFRONT_SELL_TO_US; notify email
    | is configured in Storefront Settings (sell_to_us.notify_email).
    */
    'sell_to_us' => [
        'enabled' => filter_var(env('STOREFRONT_SELL_TO_US', false), FILTER_VALIDATE_BOOLEAN),
        'max_photos' => max(1, (int) env('STOREFRONT_SELL_TO_US_MAX_PHOTOS', 6)),
        'max_photo_kb' => max(100, (int) env('STOREFRONT_SELL_TO_US_MAX_PHOTO_KB', 4096)),
    ],

];
