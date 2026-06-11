<?php

namespace App\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;

class LabelManager
{
    public function __construct(private readonly Repository $config)
    {
    }

    public function get(string $key, string $form = 'singular'): string
    {
        $label = $this->configuredLabel($key, $form);

        if ($label !== null) {
            return $label;
        }

        $singular = $this->configuredLabel($key, 'singular');

        return $singular ?? Str::headline($key);
    }

    private function configuredLabel(string $key, string $form): ?string
    {
        $value = $this->config->get("labels.{$key}.{$form}");

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
