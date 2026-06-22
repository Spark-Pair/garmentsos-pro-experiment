<?php

namespace App\Services\Updater;

use App\Services\AuditLogService;

class UpdateLogService
{
    public function __construct(protected AuditLogService $auditLogs)
    {
    }

    public function record(string $eventType, array $context = []): void
    {
        $this->auditLogs->record('updater.' . $eventType, $context, [
            'module' => 'updater',
        ]);
    }
}
