<?php

namespace App\Support;

use Illuminate\Contracts\Config\Repository;

class FeatureManager
{
    public function __construct(private readonly Repository $config)
    {
    }

    public function enabled(string $key): bool
    {
        return $this->config->get("features.{$key}") === true;
    }

    public function disabled(string $key): bool
    {
        return !$this->enabled($key);
    }

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        $features = $this->config->get('features', []);

        if (!is_array($features)) {
            return [];
        }

        return collect($features)
            ->map(fn ($value) => $value === true)
            ->all();
    }
}
