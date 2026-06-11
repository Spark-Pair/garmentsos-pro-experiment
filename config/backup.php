<?php

return [
    'temporary_backup_path' => env(
        'BACKUP_TEMP_PATH',
        storage_path('app/backups/tmp')
    ),

    'manual_backup_path' => env(
        'BACKUP_MANUAL_PATH',
        storage_path('app/backups/manual')
    ),

    'automatic_backup_path' => env(
        'BACKUP_AUTOMATIC_PATH',
        storage_path('app/backups/auto')
    ),

    'temp_retention_minutes' => (int) env('BACKUP_TEMP_RETENTION_MINUTES', 120),

    'manual_retention_count' => (int) env('BACKUP_MANUAL_RETENTION_COUNT', 30),
    'daily_retention_count' => (int) env('BACKUP_DAILY_RETENTION_COUNT', 14),
    'weekly_retention_count' => (int) env('BACKUP_WEEKLY_RETENTION_COUNT', 8),
    'monthly_retention_count' => (int) env('BACKUP_MONTHLY_RETENTION_COUNT', 12),
];
