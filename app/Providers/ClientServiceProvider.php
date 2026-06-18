<?php

namespace App\Providers;

use App\Support\ClientContext;
use App\Support\FeatureManager;
use App\Support\LabelManager;
use App\Support\ModuleManager;
use Illuminate\Support\ServiceProvider;

class ClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClientContext::class, function ($app) {
            return new ClientContext($app['config']);
        });

        $this->app->singleton(LabelManager::class, function ($app) {
            return new LabelManager($app['config']);
        });

        $this->app->singleton(FeatureManager::class, function ($app) {
            return new FeatureManager($app['config']);
        });

        $this->app->singleton(ModuleManager::class, function ($app) {
            return new ModuleManager($app['config']);
        });
    }
}
