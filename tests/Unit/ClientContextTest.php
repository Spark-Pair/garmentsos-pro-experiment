<?php

namespace Tests\Unit;

use App\Support\ClientContext;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ClientContextTest extends TestCase
{
    public function test_client_config_has_expected_default_shape(): void
    {
        $this->assertSame([
            'id' => 'default',
            'name' => 'GarmentsOS PRO',
            'version' => '0.0.0-local',
            'update_channel' => 'stable',
            'release' => [
                'build_id' => null,
                'commit' => null,
                'built_at' => null,
            ],
        ], config('client'));
    }

    public function test_context_returns_default_values(): void
    {
        $context = app(ClientContext::class);

        $this->assertSame('default', $context->id());
        $this->assertSame('GarmentsOS PRO', $context->name());
        $this->assertSame('0.0.0-local', $context->version());
        $this->assertSame('stable', $context->channel());
        $this->assertTrue($context->isDefault());
    }

    public function test_context_returns_configured_overrides(): void
    {
        Config::set('client.id', 'client-a');
        Config::set('client.name', 'Client A Garments');
        Config::set('client.version', '1.2.3');
        Config::set('client.update_channel', 'beta');

        $context = app(ClientContext::class);

        $this->assertSame('client-a', $context->id());
        $this->assertSame('Client A Garments', $context->name());
        $this->assertSame('1.2.3', $context->version());
        $this->assertSame('beta', $context->channel());
        $this->assertFalse($context->isDefault());
    }

    public function test_context_converts_values_to_an_array(): void
    {
        Config::set('client.id', 'client-b');
        Config::set('client.name', 'Client B Garments');
        Config::set('client.version', '2.0.0');
        Config::set('client.update_channel', 'stable');

        $this->assertSame([
            'id' => 'client-b',
            'name' => 'Client B Garments',
            'version' => '2.0.0',
            'update_channel' => 'stable',
        ], app(ClientContext::class)->toArray());
    }

    public function test_context_is_registered_as_a_singleton(): void
    {
        $this->assertSame(
            app(ClientContext::class),
            app(ClientContext::class)
        );
    }
}
