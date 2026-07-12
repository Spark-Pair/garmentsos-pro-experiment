<?php

namespace App\Services\Setup;

use App\Models\User;
use App\Services\Branches\ModuleBranchService;
use App\Services\Settings\AppSettingService;
use App\Services\Settings\BrandingSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FirstRunSetupService
{
    public function __construct(
        protected AppSettingService $settings,
        protected BrandingSettingsService $branding,
        protected ModuleBranchService $branches,
    ) {
    }

    public function systemStatus(): array
    {
        return [
            'database' => $this->databaseStatus(),
            'storage' => $this->pathStatus(storage_path()),
            'backup' => $this->pathStatus(storage_path('app/' . trim((string) config('backup.path', 'private/backups/database'), '/'))),
            'mode' => config('licensing.installation_mode', env('INSTALLATION_MODE', 'local_lan')),
            'app_version' => config('app.version', config('updater.current_version', env('APP_VERSION', 'local'))),
            'local_url_example' => 'http://SERVER-IP:8000',
        ];
    }

    public function existingInstallContext(): array
    {
        $usersCount = $this->usersCount();
        $businessRecordsCount = $this->businessRecordsCount();

        return [
            'users_count' => $usersCount,
            'business_records_count' => $businessRecordsCount,
            'has_existing_data' => $usersCount > 0 || $businessRecordsCount > 0,
            'dev_exists' => $this->userExists('dev'),
            'defuser_exists' => $this->userExists('defuser'),
        ];
    }

    public function complete(array $data): void
    {
        DB::transaction(function () use ($data) {
            $this->upsertUser('dev', 'dev', 'developer', $data['dev_password']);
            $this->upsertUser('defuser', 'defuser', 'owner', $data['admin_password']);

            $companyName = trim((string) ($data['company_name'] ?? ''));
            $phone = trim((string) ($data['phone'] ?? ''));
            $address = trim((string) ($data['address'] ?? ''));

            if ($companyName !== '') {
                $this->branding->save('company_name', $companyName);
                $this->branding->save('client_name', $companyName);
            }

            if ($phone !== '') {
                $this->branding->save('phone', $phone);
            }

            $this->settings->set('setup_company_address', $address, 'string');
            $this->settings->set('setup_completed', true, 'boolean');
            $this->settings->set('setup_completed_at', now()->toIso8601String(), 'string');
            $this->settings->set('setup_version', '1', 'integer');

            $this->branches->syncMainBranchDetails([
                'company_name' => $companyName,
                'name' => $companyName ?: 'Main Branch',
                'display_name' => $companyName,
                'phone' => $phone,
                'address' => $address,
                'header_text' => $companyName,
            ]);
        });

        $this->ensurePublicStorageLink();
    }

    protected function upsertUser(string $username, string $name, string $role, string $password): void
    {
        User::updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => $role,
                'status' => 'active',
            ],
        );
    }

    protected function ensurePublicStorageLink(): void
    {
        try {
            if (!File::exists(public_path('storage'))) {
                Artisan::call('storage:link');
            }
        } catch (\Throwable $e) {
            Log::warning('Setup could not create public storage link; branch logo fallback route remains available.', [
                'reason' => Str::limit($e->getMessage(), 180),
            ]);
        }
    }

    protected function databaseStatus(): array
    {
        try {
            DB::connection()->getPdo();

            return ['ok' => true, 'label' => 'Connected'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'label' => 'Unavailable: ' . Str::limit($e->getMessage(), 100)];
        }
    }

    protected function pathStatus(string $path): array
    {
        try {
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
            }

            return [
                'ok' => File::isWritable($path),
                'label' => File::isWritable($path) ? 'Writable' : 'Not writable',
                'path' => $path,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'label' => 'Unavailable: ' . Str::limit($e->getMessage(), 100), 'path' => $path];
        }
    }

    protected function businessRecordsCount(): int
    {
        $tables = [
            'customers',
            'suppliers',
            'articles',
            'orders',
            'shipments',
            'invoices',
            'customer_payments',
            'supplier_payments',
            'bank_accounts',
            'expenses',
            'vouchers',
            'fabrics',
            'productions',
            'employees',
        ];

        return collect($tables)->sum(function (string $table): int {
            try {
                return Schema::hasTable($table) ? DB::table($table)->count() : 0;
            } catch (\Throwable) {
                return 0;
            }
        });
    }

    protected function usersCount(): int
    {
        try {
            return Schema::hasTable('users') ? User::query()->count() : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function userExists(string $username): bool
    {
        try {
            return Schema::hasTable('users') && User::where('username', $username)->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
