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

            return [
                'key' => $key,
                'label' => $default['label'] ?? $key,
                'description' => $default['description'] ?? '',
                'enabled' => $override instanceof ModuleSetting ? $override->enabled : (bool) ($default['default_enabled'] ?? true),
                'visible_in_sidebar' => $override instanceof ModuleSetting ? $override->visible_in_sidebar : true,
                'has_override' => $override instanceof ModuleSetting,
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
