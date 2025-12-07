<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Passport uses encryption keys while generating secure access tokens for
    | your application. By default, the keys are stored as local files but
    | can be set via environment variables when that is more convenient.
    |
    */

    'private_key' => env('PASSPORT_PRIVATE_KEY') ?: storage_path('oauth-private.key'),

    'public_key' => env('PASSPORT_PUBLIC_KEY') ?: storage_path('oauth-public.key'),

];
