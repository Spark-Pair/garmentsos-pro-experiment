<?php

namespace App\Services;

class RestoreService
{
    public function requirements(): array
    {
        return [
            'confirmation_required' => true,
            'emergency_backup_before_restore' => true,
            'public_backups_allowed' => false,
        ];
    }
}
