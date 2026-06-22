<?php

use App\Services\Settings\LabelSettingsService;

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
