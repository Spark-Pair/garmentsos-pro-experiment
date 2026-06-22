<?php

namespace App\Services\Settings;

use App\Models\BrandingSetting;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;

class BrandingSettingsService
{
    public function __construct(
        protected SettingsCacheService $cache,
        protected AuditLogService $auditLogs,
    ) {
    }

    public function defaults(): array
    {
        return config('branding', []);
    }

    public function all(): array
    {
        $settings = $this->cache->all()['branding'];

        return collect($this->defaults())->mapWithKeys(function ($default, string $key) use ($settings) {
            $override = $settings->get($key);

            return [$key => [
                'key' => $key,
                'value' => $override instanceof BrandingSetting ? $override->value : $default,
                'default' => $default,
                'has_override' => $override instanceof BrandingSetting,
            ]];
        })->all();
    }

    public function value(string $key, ?string $fallback = null): ?string
    {
        return $this->all()[$key]['value'] ?? $fallback;
    }

    public function save(string $key, string $value): BrandingSetting
    {
        if (!array_key_exists($key, $this->defaults())) {
            throw new \InvalidArgumentException('Unknown branding key.');
        }

        $this->assertSafeValue($key, $value);

        $setting = BrandingSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => str_contains($key, 'color') ? 'color' : 'string',
                'updated_by' => Auth::id(),
                'created_by' => Auth::id(),
            ],
        );

        $this->cache->forget();
        $this->auditLogs->record('settings.branding_saved', [
            'key' => $key,
            'value' => $value,
        ], ['module' => 'developer_settings']);

        return $setting;
    }

    protected function assertSafeValue(string $key, string $value): void
    {
        if (str_contains($key, 'color')) {
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                throw new \InvalidArgumentException('Theme color must be a hex color.');
            }

            return;
        }

        if (mb_strlen($value) > 120 || $value !== strip_tags($value)) {
            throw new \InvalidArgumentException('Branding text must be plain text up to 120 characters.');
        }
    }
}
