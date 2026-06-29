<?php

namespace App\Services\Updater;

use App\Services\Settings\AppSettingService;
use Illuminate\Support\Facades\File;

class InstalledVersionService
{
    public function __construct(protected AppSettingService $settings)
    {
    }

    public function currentVersion(): string
    {
        $manifest = $this->installedManifest();
        $version = $manifest['version']
            ?? $manifest['app_version']
            ?? $manifest['current_version']
            ?? null;

        if (is_string($version) && trim($version) !== '') {
            return trim($version);
        }

        $configured = (string) config('updater.current_version', '');
        if ($configured !== '' && $configured !== '0.0.0') {
            return $configured;
        }

        $setting = $this->settings->get('installed_version');
        if (is_string($setting) && trim($setting) !== '') {
            return trim($setting);
        }

        return 'local';
    }

    public function source(): string
    {
        if ($this->installedManifest()) {
            return 'installed manifest';
        }

        $configured = (string) config('updater.current_version', '');
        if ($configured !== '' && $configured !== '0.0.0') {
            return 'config/env';
        }

        $setting = $this->settings->get('installed_version');

        return is_string($setting) && trim($setting) !== '' ? 'app setting' : 'local fallback';
    }

    public function isDeveloperSourceMode(): bool
    {
        return $this->currentVersion() === 'local' || $this->source() === 'local fallback';
    }

    public function manifestConfigured(): bool
    {
        return $this->installedManifestPath() !== null;
    }

    public function installedManifest(): ?array
    {
        $path = $this->installedManifestPath();

        if (!$path) {
            return null;
        }

        $contents = preg_replace('/^\xEF\xBB\xBF/', '', File::get($path));
        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function installedManifestPath(): ?string
    {
        foreach ((array) config('updater.installed_manifest_paths', []) as $path) {
            $resolved = $this->resolvePath((string) $path);
            if ($resolved && File::isFile($resolved)) {
                return $resolved;
            }
        }

        return null;
    }

    protected function resolvePath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }
}
