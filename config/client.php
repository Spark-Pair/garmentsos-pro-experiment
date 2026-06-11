<?php

return [
    'id' => env('APP_CLIENT', 'default'),
    'name' => env('APP_CLIENT_NAME', 'GarmentsOS PRO'),
    'version' => env('APP_VERSION', '0.0.0-local'),
    'update_channel' => env('APP_UPDATE_CHANNEL', 'stable'),

    'release' => [
        'build_id' => null,
        'commit' => null,
        'built_at' => null,
    ],
];
