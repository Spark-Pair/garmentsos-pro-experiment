<?php

return [
    'enabled' => env('UPDATER_ENABLED', false),
    'manifest_url' => env('UPDATER_MANIFEST_URL', ''),
    'public_key' => env('UPDATER_PUBLIC_KEY', ''),
    'channel' => env('UPDATER_CHANNEL', 'stable'),
    'require_signature' => env('UPDATER_REQUIRE_SIGNATURE', true),
    'current_version' => env('APP_VERSION', '0.0.0'),
    'app_id' => 'garmentsos-pro',
    'installation_mode' => env('INSTALLATION_MODE', 'local_lan'),
    'temp_path' => 'private/updater',
    'allowed_url_schemes' => ['https'],
    'allowed_domains' => [],
];
