<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Licensing Enforcement
    |--------------------------------------------------------------------------
    |
    | Keep disabled by default. When disabled, licensing middleware and services
    | must not change normal application behavior.
    |
    */
    'enabled' => env('LICENSE_ENABLED', env('LICENSE_ENFORCEMENT_ENABLED', false)),

    'client_id' => env('LICENSE_CLIENT_ID', ''),

    'client_name' => env('LICENSE_CLIENT_NAME', ''),

    'license_key' => env('LICENSE_KEY', ''),

    'expires_at' => env('LICENSE_EXPIRES_AT', ''),

    'server_url' => env('LICENSE_CHECK_URL', env('LICENSE_SERVER_URL', 'https://sparkpair.dev/api/licenses/verify')),

    'last_check_at' => env('LICENSE_LAST_CHECK_AT', ''),

    'status' => env('LICENSE_STATUS', 'active'),

    'installation_mode' => env('INSTALLATION_MODE', 'local_lan'),

    /*
    |--------------------------------------------------------------------------
    | Public Verification Key
    |--------------------------------------------------------------------------
    |
    | This is a public key only. Never ship private signing keys to client PCs.
    |
    */
    'public_key' => env('LICENSE_PUBLIC_KEY', ''),

    'offline_grace_days' => (int) env('LICENSE_GRACE_DAYS', env('LICENSE_OFFLINE_GRACE_DAYS', 7)),

    'expiring_soon_days' => (int) env('LICENSE_EXPIRING_SOON_DAYS', 14),

    'cache_path' => storage_path('app/license/license.json'),

    'verify_cache_path' => storage_path('app/license/verify-cache.json'),

    'identity_path' => storage_path('app/license/installation.json'),

    'install_id_path' => storage_path('app/install-id.txt'),

    'request_timeout_seconds' => 10,

    'default_enforcement_mode' => 'readonly',
];
