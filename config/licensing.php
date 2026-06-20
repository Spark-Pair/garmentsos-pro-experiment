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
    'enabled' => env('LICENSE_ENFORCEMENT_ENABLED', false),

    'server_url' => env('LICENSE_SERVER_URL', ''),

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

    'offline_grace_days' => (int) env('LICENSE_OFFLINE_GRACE_DAYS', 7),

    'cache_path' => storage_path('app/license/license.json'),

    'identity_path' => storage_path('app/license/installation.json'),

    'request_timeout_seconds' => 10,

    'default_enforcement_mode' => 'readonly',
];
