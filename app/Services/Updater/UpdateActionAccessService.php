<?php

namespace App\Services\Updater;

use Illuminate\Http\Request;

class UpdateActionAccessService
{
    public function canRunFrom(Request $request): bool
    {
        if (!(bool) config('updater.actions_server_pc_only', true)) {
            return true;
        }

        $host = $this->normalizeHost($request->getHost());
        $ip = trim((string) $request->ip());

        return $this->isAllowedHost($host) || $this->isAllowedIp($host) || $this->isAllowedIp($ip);
    }

    public function denialMessage(): string
    {
        return 'Updates can only be started from the server PC. Open GarmentsOS on the server using http://localhost:8000 or http://127.0.0.1:8000, then run the update.';
    }

    private function isAllowedHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        return in_array($host, $this->configuredValues('server_pc_hosts'), true);
    }

    private function isAllowedIp(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (in_array($value, $this->configuredValues('server_pc_ips'), true)) {
            return true;
        }

        return $value === '::1' || str_starts_with($value, '127.');
    }

    private function configuredValues(string $key): array
    {
        return collect((array) config('updater.' . $key, []))
            ->map(fn ($value) => $this->normalizeHost((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeHost(string $host): string
    {
        return trim(strtolower($host), " \t\n\r\0\x0B[]");
    }
}
