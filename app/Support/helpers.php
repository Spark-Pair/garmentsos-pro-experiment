<?php

use App\Services\Settings\LabelSettingsService;
use App\Services\Settings\ModuleAvailabilityService;

if (!function_exists('label_text')) {
    function label_text(string $key, ?string $fallback = null): string
    {
        try {
            return app(LabelSettingsService::class)->text($key, $fallback);
        } catch (Throwable) {
            return $fallback ?? (string) config('labels.' . $key, $key);
        }
    }
}

if (!function_exists('module_enabled')) {
    function module_enabled(string $key): bool
    {
        try {
            return app(ModuleAvailabilityService::class)->isEffectivelyVisibleInSidebar($key);
        } catch (Throwable) {
            return true;
        }
    }
}
