<?php

namespace App\Services\Settings;

class DeveloperSettingsService
{
    public function __construct(
        public readonly LabelSettingsService $labels,
        public readonly ModuleSettingsService $modules,
        public readonly FeatureFlagService $features,
        public readonly BrandingSettingsService $branding,
        public readonly SettingsCacheService $cache,
    ) {
    }
}
