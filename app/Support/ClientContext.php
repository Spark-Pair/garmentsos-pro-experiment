<?php

namespace App\Support;

use Illuminate\Contracts\Config\Repository;

class ClientContext
{
    public function __construct(private readonly Repository $config)
    {
    }

    public function id(): string
    {
        return (string) $this->config->get('client.id', 'default');
    }

    public function name(): string
    {
        return (string) $this->config->get('client.name', 'GarmentsOS PRO');
    }

    public function version(): string
    {
        return (string) $this->config->get('client.version', '0.0.0-local');
    }

    public function channel(): string
    {
        return (string) $this->config->get('client.update_channel', 'stable');
    }

    public function isDefault(): bool
    {
        return $this->id() === 'default';
    }

    /**
     * @return array{id: string, name: string, version: string, update_channel: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'name' => $this->name(),
            'version' => $this->version(),
            'update_channel' => $this->channel(),
        ];
    }
}
