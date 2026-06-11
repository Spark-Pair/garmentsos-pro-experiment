<?php

$basePath = env('GARMENTSOS_BASE_PATH', base_path());

return [
    'external_mode' => (bool) env('GARMENTSOS_EXTERNAL_MODE', false),

    'base_path' => $basePath,
    'data_path' => env('GARMENTSOS_DATA_PATH', storage_path('app')),
    'runtime_path' => env('GARMENTSOS_RUNTIME_PATH', storage_path()),
    'backup_path' => env('GARMENTSOS_BACKUP_PATH', storage_path('app/backups')),
    'log_path' => env('GARMENTSOS_LOG_PATH', storage_path('logs')),
    'uploads_path' => env('GARMENTSOS_UPLOADS_PATH', storage_path('app/public/uploads')),

    'minimum_free_space_mb' => (int) env('GARMENTSOS_MINIMUM_FREE_SPACE_MB', 1024),

    'application_path' => base_path(),
    'storage_path' => storage_path(),
    'public_path' => public_path(),
];
