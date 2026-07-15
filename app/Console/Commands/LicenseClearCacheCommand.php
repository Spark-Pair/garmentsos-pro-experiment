<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LicenseClearCacheCommand extends Command
{
    protected $signature = 'license:clear-cache';

    protected $description = 'Clear GarmentsOS license response caches without deleting install identity.';

    public function handle(): int
    {
        $paths = [
            'verify_cache' => (string) config('licensing.verify_cache_path'),
            'registration_cache' => (string) config('licensing.registration_cache_path'),
            'last_response_cache' => (string) config('licensing.last_response_cache_path'),
        ];

        $rows = [];
        foreach ($paths as $name => $path) {
            if ($path !== '' && File::exists($path)) {
                File::delete($path);
                $rows[] = [$name, 'deleted', $path];
                continue;
            }

            $rows[] = [$name, 'not found', $path];
        }

        $this->table(['cache', 'status', 'path'], $rows);
        $this->line('Installation identity was preserved: ' . (string) config('licensing.identity_path'));
        $this->line('Install ID was preserved: ' . (string) config('licensing.install_id_path'));

        return self::SUCCESS;
    }
}
