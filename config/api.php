<?php

/**
 * Staff/integration Passport API (POST /api/login and auth:api routes).
 * Empty allowlists deny all API logins — set dedicated integration user ids/usernames only.
 */
return [
    'login_user_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('API_LOGIN_USER_IDS', ''))
    ))),

    'login_usernames' => array_values(array_filter(array_map(
        static fn (string $v): string => strtolower(trim($v)),
        explode(',', (string) env('API_LOGIN_USERNAMES', ''))
    ))),

    'token_expire_days' => max(1, (int) env('API_TOKEN_EXPIRE_DAYS', 7)),

    /**
     * Scopes issued to allowlisted integration users on successful login.
     *
     * @var list<string>
     */
    'login_scopes' => [
        'user-read',
        'accounts-orders',
        'accounts-catalog',
        'contacts-read',
        'rewards',
    ],
];
