<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureFeatureEnabledTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('feature:shipments')
            ->get('/_test/features/enabled', fn () => response('enabled'));

        Route::middleware('feature:updates')
            ->get('/_test/features/disabled', fn () => response('disabled'));

        Route::middleware('feature:missing_feature')
            ->get('/_test/features/missing', fn () => response('missing'));

        Route::middleware('feature:invalid_feature')
            ->get('/_test/features/invalid', fn () => response('invalid'));

        Route::middleware('feature')
            ->get('/_test/features/no-parameter', fn () => response('no parameter'));

        Route::middleware(['feature:shipments', 'feature:orders'])
            ->get('/_test/features/stacked', fn () => response('stacked'));

        Route::get('/_test/features/unrelated', fn () => response('unrelated'));
    }

    public function test_enabled_feature_allows_request(): void
    {
        $this->get('/_test/features/enabled')
            ->assertOk()
            ->assertSeeText('enabled');
    }

    public function test_disabled_feature_returns_not_found(): void
    {
        $this->get('/_test/features/disabled')->assertNotFound();
    }

    public function test_missing_feature_returns_not_found(): void
    {
        $this->get('/_test/features/missing')->assertNotFound();
    }

    public function test_invalid_config_value_returns_not_found(): void
    {
        Config::set('features.invalid_feature', 'true');

        $this->get('/_test/features/invalid')->assertNotFound();
    }

    public function test_middleware_without_parameter_returns_not_found(): void
    {
        $this->get('/_test/features/no-parameter')->assertNotFound();
    }

    public function test_json_request_receives_generic_json_not_found(): void
    {
        $this->getJson('/_test/features/disabled')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not Found']);
    }

    public function test_unrelated_route_remains_accessible(): void
    {
        $this->get('/_test/features/unrelated')
            ->assertOk()
            ->assertSeeText('unrelated');
    }

    public function test_stacked_feature_middleware_allows_request_when_all_features_are_enabled(): void
    {
        $this->get('/_test/features/stacked')
            ->assertOk()
            ->assertSeeText('stacked');
    }

    public function test_stacked_feature_middleware_blocks_request_when_one_feature_is_disabled(): void
    {
        Config::set('features.orders', false);

        $this->get('/_test/features/stacked')->assertNotFound();
    }
}
