<?php

namespace App\Services\Settings;

class FeatureAvailabilityService
{
    public function __construct(protected FeatureFlagService $features)
    {
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
        if ($localEnabled === false) {
            return $this->state(
                $flagKey,
                true,
                false,
                'disabled_locally',
                $defaultEnabled,
                null,
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
            $available ? ($localEnabled === null ? 'default_allowed' : 'available') : 'disabled_locally',
            $defaultEnabled,
            null,
            $localEnabled,
            $available ? 'Available.' : 'This feature is disabled.',
            $local,
        );
    }

    public function isEffectivelyEnabled(string $flagKey): bool
    {
        return (bool) $this->effectiveState($flagKey)['available'];
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
