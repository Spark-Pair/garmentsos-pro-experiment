<?php

namespace App\Services\Settings;

use App\Services\Licensing\LicenseService;

class FeatureAvailabilityService
{
    public function __construct(
        protected FeatureFlagService $features,
        protected LicenseService $licenses,
    ) {
    }

    public function all(): array
    {
        return collect($this->features->registry())
            ->keys()
            ->map(fn (string $key) => $this->effectiveState($key))
            ->values()
            ->all();
    }

    public function effectiveState(string $flagKey): array
    {
        $registry = $this->features->registry();

        if (!array_key_exists($flagKey, $registry)) {
            return $this->state($flagKey, false, false, 'unknown_feature', false, null, null, 'Unknown feature.');
        }

        $local = $this->features->effectiveState($flagKey);
        $defaultEnabled = (bool) ($registry[$flagKey]['default_enabled'] ?? false);
        $localEnabled = ($local['has_override'] ?? false) ? (bool) $local['enabled'] : null;
        $license = $this->licenseState($flagKey);
        $licenseAllowed = $license['allowed'];

        if ($licenseAllowed === false) {
            return $this->state(
                $flagKey,
                true,
                false,
                'disabled_by_license',
                $defaultEnabled,
                false,
                $localEnabled,
                'This feature is not included in the active license.',
                $local,
            );
        }

        if ($localEnabled === false) {
            return $this->state(
                $flagKey,
                true,
                false,
                'disabled_locally',
                $defaultEnabled,
                $licenseAllowed,
                false,
                'This feature is disabled in developer settings.',
                $local,
            );
        }

        $available = $localEnabled ?? $defaultEnabled;

        return $this->state(
            $flagKey,
            true,
            $available,
            $available
                ? ($license['unavailable'] ? 'licensing_unavailable' : ($localEnabled === null ? 'default_allowed' : 'available'))
                : 'disabled_locally',
            $defaultEnabled,
            $licenseAllowed,
            $localEnabled,
            $available ? 'Available.' : 'This feature is disabled.',
            $local,
        );
    }

    public function isEffectivelyEnabled(string $flagKey): bool
    {
        return (bool) $this->effectiveState($flagKey)['available'];
    }

    protected function licenseState(string $flagKey): array
    {
        if (!$this->licenses->enabled()) {
            return ['allowed' => null, 'unavailable' => false];
        }

        try {
            $status = $this->licenses->currentStatus();
        } catch (\Throwable) {
            return ['allowed' => null, 'unavailable' => true];
        }

        if (!$status->isAllowed() && $status->features === []) {
            return ['allowed' => null, 'unavailable' => true];
        }

        if ($status->features === []) {
            return ['allowed' => null, 'unavailable' => false];
        }

        return ['allowed' => in_array($flagKey, $status->features, true), 'unavailable' => false];
    }

    protected function state(
        string $key,
        bool $known,
        bool $available,
        string $reason,
        bool $defaultEnabled,
        ?bool $licenseAllowed,
        ?bool $localEnabled,
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
            'message' => $message,
        ]);
    }
}
