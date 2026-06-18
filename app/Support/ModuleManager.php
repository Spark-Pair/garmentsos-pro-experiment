<?php

namespace App\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

class ModuleManager
{
    public function __construct(private readonly ConfigRepository $config)
    {
    }

    public function all(): array
    {
        $modules = $this->config->get('modules.modules', []);

        return is_array($modules) ? $modules : [];
    }

    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function get(string $key): ?array
    {
        $module = $this->all()[$key] ?? null;

        return is_array($module) ? $module : null;
    }

    public function featureKey(string $key): ?string
    {
        $feature = $this->get($key)['feature'] ?? null;

        return is_string($feature) && $feature !== '' ? $feature : null;
    }

    public function dependencies(string $key): array
    {
        $dependencies = $this->get($key)['dependencies'] ?? [];

        return is_array($dependencies) ? array_values($dependencies) : [];
    }
}
