<?php

namespace App\Services\Settings;

use App\Models\BrandingSetting;
use App\Models\FeatureFlag;
use App\Models\LabelOverride;
use App\Models\ModuleSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SettingsCacheService
{
    public const CACHE_KEY = 'developer_settings:v1';

    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return [
                'labels' => $this->tableExists('label_overrides')
                    ? LabelOverride::query()->get()->keyBy(fn (LabelOverride $label) => $label->locale . ':' . $label->label_key)
                    : collect(),
                'branding' => $this->tableExists('branding_settings')
                    ? BrandingSetting::query()->get()->keyBy('key')
                    : collect(),
                'modules' => $this->tableExists('module_settings')
                    ? ModuleSetting::query()->get()->keyBy('module_key')
                    : collect(),
                'features' => $this->tableExists('feature_flags')
                    ? FeatureFlag::query()->get()->keyBy('flag_key')
                    : collect(),
            ];
        });
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
