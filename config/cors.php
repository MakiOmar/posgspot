<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| Allowed origins are driven by the CORS_ALLOWED_ORIGINS env variable
| (comma-separated list). This keeps the public Qwik storefront (and the
| future mobile web views) able to call the API while avoiding a blanket
| "*" which cannot be combined with credentials (needed for Sanctum).
|
| Example (.env on the production server):
|   CORS_ALLOWED_ORIGINS=http://localhost:5173,https://shop.gamesspoteg.com
|   CORS_SUPPORTS_CREDENTIALS=true
|
| To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
|
*/

// Build the allowed origins list from env, falling back to local dev origins.
$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173'))
)));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Required for Sanctum cookie-based auth from the storefront.
    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', true),

];
