<?php

namespace App\Services\Settings;

use App\Models\ModuleSetting;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;

class ModuleSettingsService
{
    public function __construct(
        protected SettingsCacheService $cache,
        protected AuditLogService $auditLogs,
    ) {
    }

    public function registry(): array
    {
        return config('modules', []);
    }

    public function all(): array
    {
        $settings = $this->cache->all()['modules'];

        return collect($this->registry())->map(function (array $default, string $key) use ($settings) {
            $override = $settings->get($key);
            $enabled = $override instanceof ModuleSetting ? $override->enabled : (bool) ($default['default_enabled'] ?? true);
            $visible = $override instanceof ModuleSetting ? $override->visible_in_sidebar : true;

            return [
                'key' => $key,
                'label' => $default['label'] ?? $key,
                'description' => $default['description'] ?? '',
                'enabled' => $enabled,
                'visible_in_sidebar' => $visible,
                'effective_enabled' => $enabled,
                'effective_visible_in_sidebar' => $enabled && $visible,
                'has_override' => $override instanceof ModuleSetting,
                'reason' => $override instanceof ModuleSetting ? $override->reason : null,
            ];
        })->values()->all();
    }

    public function enabled(string $key): bool
    {
        foreach ($this->all() as $module) {
            if ($module['key'] === $key) {
                return (bool) $module['enabled'];
            }
        }

        return true;
    }

    public function visibleInSidebar(string $key): bool
    {
        foreach ($this->all() as $module) {
            if ($module['key'] === $key) {
                return (bool) $module['effective_visible_in_sidebar'];
            }
        }

        return true;
    }

    public function effectiveState(string $key): array
    {
        foreach ($this->all() as $module) {
            if ($module['key'] === $key) {
                return $module;
            }
        }

        return [
            'key' => $key,
            'label' => $key,
            'description' => '',
            'enabled' => true,
            'visible_in_sidebar' => true,
            'effective_enabled' => true,
            'effective_visible_in_sidebar' => true,
            'has_override' => false,
            'reason' => null,
        ];
    }

    public function save(string $key, bool $enabled, bool $visible = true): ModuleSetting
    {
        if (!array_key_exists($key, $this->registry())) {
            throw new \InvalidArgumentException('Unknown module key.');
        }

        $setting = ModuleSetting::updateOrCreate(
            ['module_key' => $key],
            [
                'enabled' => $enabled,
                'visible_in_sidebar' => $visible,
                'updated_by' => Auth::id(),
                'created_by' => Auth::id(),
            ],
        );

        $this->cache->forget();
        $this->auditLogs->record('settings.module_saved', [
            'module_key' => $key,
            'enabled' => $enabled,
            'visible_in_sidebar' => $visible,
        ], ['module' => 'developer_settings']);

        return $setting;
    }
}
