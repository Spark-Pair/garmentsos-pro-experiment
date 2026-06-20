<?php

namespace App\Services\Licensing;

use Carbon\CarbonInterface;

class LicenseStatus
{
    public function __construct(
        public readonly string $state,
        public readonly string $enforcement = 'none',
        public readonly string $message = '',
        public readonly ?CarbonInterface $expiresAt = null,
        public readonly ?CarbonInterface $graceUntil = null,
        public readonly array $modules = [],
        public readonly array $features = [],
        public readonly array $brands = [],
        public readonly string $updateChannel = 'stable',
        public readonly string $source = 'none',
    ) {
    }

    public static function disabled(): self
    {
        return new self(
            state: 'disabled',
            enforcement: 'none',
            message: 'License enforcement is disabled.',
        );
    }

    public static function valid(string $source = 'database', array $payload = []): self
    {
        return new self(
            state: 'valid',
            enforcement: 'none',
            message: $payload['message'] ?? 'License is valid.',
            expiresAt: $payload['expires_at'] ?? null,
            graceUntil: $payload['grace_until'] ?? null,
            modules: $payload['modules'] ?? [],
            features: $payload['features'] ?? [],
            brands: $payload['brands'] ?? [],
            updateChannel: $payload['update_channel'] ?? 'stable',
            source: $source,
        );
    }

    public static function problem(string $state, string $enforcement, string $message, array $payload = []): self
    {
        return new self(
            state: $state,
            enforcement: $enforcement,
            message: $message,
            expiresAt: $payload['expires_at'] ?? null,
            graceUntil: $payload['grace_until'] ?? null,
            modules: $payload['modules'] ?? [],
            features: $payload['features'] ?? [],
            brands: $payload['brands'] ?? [],
            updateChannel: $payload['update_channel'] ?? 'stable',
            source: $payload['source'] ?? 'database',
        );
    }

    public function isAllowed(): bool
    {
        return in_array($this->state, ['disabled', 'valid', 'offline_grace'], true);
    }

    public function shouldReadOnly(): bool
    {
        return $this->enforcement === 'readonly';
    }

    public function shouldBlock(): bool
    {
        return $this->enforcement === 'blocked';
    }

    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'enforcement' => $this->enforcement,
            'message' => $this->message,
            'expires_at' => $this->expiresAt?->toDateTimeString(),
            'grace_until' => $this->graceUntil?->toDateTimeString(),
            'modules' => $this->modules,
            'features' => $this->features,
            'brands' => $this->brands,
            'update_channel' => $this->updateChannel,
            'source' => $this->source,
        ];
    }
}
