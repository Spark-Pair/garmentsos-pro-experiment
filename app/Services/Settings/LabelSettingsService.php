<?php

namespace App\Services\Settings;

use App\Models\LabelOverride;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;

class LabelSettingsService
{
    public function __construct(
        protected SettingsCacheService $cache,
        protected AuditLogService $auditLogs,
        protected SettingsValueGuard $valueGuard,
    ) {
    }

    public function defaults(): array
    {
        return config('labels', []);
    }

    public function text(string $key, ?string $fallback = null, string $locale = 'en'): string
    {
        $settings = $this->cache->all();
        $override = $settings['labels']->get($locale . ':' . $key);

        if ($override instanceof LabelOverride && $override->override_text !== '') {
            return $override->override_text;
        }

        return $fallback ?? (string) config('labels.' . $key, $key);
    }

    public function save(string $key, string $text, string $locale = 'en'): LabelOverride
    {
        $this->assertKnownKey($key);
        $this->assertSafeText($text);

        $label = LabelOverride::updateOrCreate(
            ['label_key' => $key, 'locale' => $locale],
            [
                'default_text' => (string) config('labels.' . $key, ''),
                'override_text' => $text,
                'updated_by' => Auth::id(),
                'created_by' => Auth::id(),
            ],
        );

        $this->cache->forget();
        $this->auditLogs->record('settings.label_saved', [
            'label_key' => $key,
            'locale' => $locale,
            'override_text' => $text,
        ], ['module' => 'developer_settings']);

        return $label;
    }

    public function reset(string $key, string $locale = 'en'): void
    {
        $this->assertKnownKey($key);

        LabelOverride::where('label_key', $key)->where('locale', $locale)->delete();
        $this->cache->forget();
        $this->auditLogs->record('settings.label_reset', [
            'label_key' => $key,
            'locale' => $locale,
        ], ['module' => 'developer_settings']);
    }

    public function assertKnownKey(string $key): void
    {
        if (!array_key_exists($key, $this->defaults())) {
            throw new \InvalidArgumentException('Unknown label key.');
        }
    }

    public function assertSafeText(string $text): void
    {
        if ($text === '' || mb_strlen($text) > 80 || $text !== strip_tags($text)) {
            throw new \InvalidArgumentException('Label text must be plain text from 1 to 80 characters.');
        }

        $this->valueGuard->assertNoSecretLikeValue($text);
    }
}
