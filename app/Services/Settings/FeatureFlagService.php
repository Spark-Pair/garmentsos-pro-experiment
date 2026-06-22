<?php

namespace App\Services\Settings;

use App\Models\FeatureFlag;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;

class FeatureFlagService
{
    public function __construct(
        protected SettingsCacheService $cache,
        protected AuditLogService $auditLogs,
    ) {
    }

    public function registry(): array
    {
        return config('features', []);
    }

    public function all(): array
    {
        $settings = $this->cache->all()['features'];

        return collect($this->registry())->map(function (array $default, string $key) use ($settings) {
            $override = $settings->get($key);

            return [
                'key' => $key,
                'label' => $default['label'] ?? $key,
                'description' => $default['description'] ?? '',
                'enabled' => $override instanceof FeatureFlag ? $override->enabled : (bool) ($default['default_enabled'] ?? false),
                'has_override' => $override instanceof FeatureFlag,
            ];
        })->values()->all();
    }

    public function enabled(string $key): bool
    {
        foreach ($this->all() as $flag) {
            if ($flag['key'] === $key) {
                return (bool) $flag['enabled'];
            }
        }

        return false;
    }

    public function save(string $key, bool $enabled): FeatureFlag
    {
        if (!array_key_exists($key, $this->registry())) {
            throw new \InvalidArgumentException('Unknown feature flag key.');
        }

        $flag = FeatureFlag::updateOrCreate(
            ['flag_key' => $key],
            [
                'enabled' => $enabled,
                'type' => 'boolean',
                'description' => $this->registry()[$key]['description'] ?? null,
                'updated_by' => Auth::id(),
                'created_by' => Auth::id(),
            ],
        );

        $this->cache->forget();
        $this->auditLogs->record('settings.feature_saved', [
            'flag_key' => $key,
            'enabled' => $enabled,
        ], ['module' => 'developer_settings']);

        return $flag;
    }
}
