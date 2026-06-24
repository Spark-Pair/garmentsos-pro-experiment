<?php

namespace App\Services\Settings;

use App\Services\Licensing\LicenseService;

class ModuleAvailabilityService
{
    public function __construct(
        protected ModuleSettingsService $modules,
        protected LicenseService $licenses,
    ) {
    }

    public function all(): array
    {
        return collect($this->modules->registry())
            ->keys()
            ->map(fn (string $key) => $this->effectiveState($key))
            ->values()
            ->all();
    }

    public function effectiveState(string $moduleKey): array
    {
        $registry = $this->modules->registry();

        if (!array_key_exists($moduleKey, $registry)) {
            return $this->state(
                $moduleKey,
                false,
                false,
                'unknown_module',
                false,
                null,
                null,
                false,
                'Unknown module.',
            );
        }

        $local = $this->modules->effectiveState($moduleKey);
        $defaultEnabled = (bool) ($registry[$moduleKey]['default_enabled'] ?? true);
        $localEnabled = ($local['has_override'] ?? false) ? (bool) $local['enabled'] : null;
        $localVisible = ($local['has_override'] ?? false) ? (bool) $local['visible_in_sidebar'] : null;
        $license = $this->licenseState($moduleKey);
        $licenseAllowed = $license['allowed'];

        if ($licenseAllowed === false) {
            return $this->state(
                $moduleKey,
                true,
                false,
                'disabled_by_license',
                $defaultEnabled,
                false,
                $localEnabled,
                false,
                'This module is not included in the active license.',
                $local,
            );
        }

        if ($localEnabled === false) {
            return $this->state(
                $moduleKey,
                true,
                false,
                'disabled_locally',
                $defaultEnabled,
                $licenseAllowed,
                false,
                false,
                'This module is disabled in developer settings.',
                $local,
            );
        }

        $available = $localEnabled ?? $defaultEnabled;
        $visible = $available && ($localVisible ?? true);
        $reason = $license['unavailable']
            ? 'licensing_unavailable'
            : ($localEnabled === null ? 'default_allowed' : 'available');

        return $this->state(
            $moduleKey,
            true,
            $available,
            $available ? $reason : 'disabled_locally',
            $defaultEnabled,
            $licenseAllowed,
            $localEnabled,
            $visible,
            $available ? 'Available.' : 'This module is disabled.',
            $local,
        );
    }

    public function isEffectivelyEnabled(string $moduleKey): bool
    {
        return (bool) $this->effectiveState($moduleKey)['available'];
    }

    public function isEffectivelyVisibleInSidebar(string $moduleKey): bool
    {
        return (bool) $this->effectiveState($moduleKey)['visible_in_sidebar'];
    }

    protected function licenseState(string $moduleKey): array
    {
        if (!$this->licenses->enabled()) {
            return ['allowed' => null, 'unavailable' => false];
        }

        try {
            $status = $this->licenses->currentStatus();
        } catch (\Throwable) {
            return ['allowed' => null, 'unavailable' => true];
        }

        if (!$status->isAllowed()) {
            return ['allowed' => null, 'unavailable' => true];
        }

        if ($status->modules === []) {
            return ['allowed' => null, 'unavailable' => false];
        }

        return ['allowed' => in_array($moduleKey, $status->modules, true), 'unavailable' => false];
    }

    protected function state(
        string $key,
        bool $known,
        bool $available,
        string $reason,
        bool $defaultEnabled,
        ?bool $licenseAllowed,
        ?bool $localEnabled,
        bool $visible,
        string $message,
        array $local = [],
    ): array {
        return array_merge($local, [
            'key' => $key,
            'known' => $known,
            'available' => $available,
            'reason' => $reason,
            'default_enabled' => $defaultEnabled,
            'license_allowed' => $licenseAllowed,
            'local_enabled' => $localEnabled,
            'effective_enabled' => $available,
            'effective_visible_in_sidebar' => $visible,
            'visible_in_sidebar' => $visible,
            'message' => $message,
        ]);
    }
}
