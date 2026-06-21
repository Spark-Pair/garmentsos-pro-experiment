<?php

return [
    'disk' => 'local',

    'path' => env('BACKUP_PRIVATE_PATH', 'private/backups/database'),

    'allowed_drivers' => [
        'sqlite',
    ],

    'filename_prefix' => 'garmentsos_backup',

    'metadata_enabled' => true,

    'checksum_algorithm' => 'sha256',

    'retention' => [
        'enabled' => false,
        'keep_latest' => 30,
        'keep_days' => 90,
    ],

    'max_list_items' => 100,

    'lock_seconds' => 300,
];
