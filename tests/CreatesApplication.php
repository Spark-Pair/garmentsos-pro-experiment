<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        if (($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null) === 'testing') {
            foreach (['config.php', 'routes-v7.php', 'packages.php', 'services.php'] as $cacheFile) {
                $path = __DIR__.'/../bootstrap/cache/'.$cacheFile;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
