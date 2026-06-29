<?php

namespace App\Services\Settings;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;

class AppSettingService
{
    public function get(string $key, mixed $default = null, string $scope = 'installation', ?int $scopeId = null): mixed
    {
        if (!$this->tableReady()) {
            return $default;
        }

        $setting = AppSetting::query()
            ->where('scope', $scope)
            ->where('scope_id', $scopeId)
            ->where('key', $key)
            ->where('is_active', true)
            ->first();

        if (!$setting) {
            return $default;
        }

        return $this->castValue($setting->value, $setting->type);
    }

    public function set(string $key, mixed $value, string $type = 'string', string $scope = 'installation', ?int $scopeId = null): AppSetting
    {
        return AppSetting::updateOrCreate(
            [
                'scope' => $scope,
                'scope_id' => $scopeId,
                'key' => $key,
            ],
            [
                'value' => $this->serializeValue($value, $type),
                'type' => $type,
                'is_active' => true,
                'updated_by' => auth()->id(),
                'created_by' => auth()->id(),
            ],
        );
    }

    public function bool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    public function tableReady(): bool
    {
        try {
            return Schema::hasTable('app_settings');
        } catch (\Throwable) {
            return false;
        }
    }

    protected function castValue(?string $value, string $type): mixed
    {
        return match ($type) {
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer', 'int' => (int) $value,
            'json', 'array' => json_decode((string) $value, true) ?: [],
            default => $value,
        };
    }

    protected function serializeValue(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean', 'bool' => $value ? '1' : '0',
            'json', 'array' => json_encode($value, JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
    }
}
