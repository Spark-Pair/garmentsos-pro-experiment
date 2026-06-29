<?php

namespace App\Services\Setup;

use App\Services\Settings\AppSettingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetupStatusService
{
    public function __construct(protected AppSettingService $settings)
    {
    }

    public function isComplete(): bool
    {
        $explicit = $this->setupCompletedSetting();

        if ($explicit !== null) {
            return $explicit;
        }

        if ($this->isForceEnabled()) {
            return false;
        }

        return $this->isLegacyExistingInstall();
    }

    public function requiresFirstRunSetup(): bool
    {
        return $this->canCheck() && !$this->isComplete();
    }

    public function isLegacyExistingInstall(): bool
    {
        return $this->existingUsersCount() > 0 || $this->businessRecordsCount() > 0;
    }

    public function isForceEnabled(): bool
    {
        return (bool) config('app.first_run_setup_force', false);
    }

    public function canCheck(): bool
    {
        return $this->settings->tableReady();
    }

    protected function setupCompletedSetting(): ?bool
    {
        $value = $this->settings->get('setup_completed', null);

        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    protected function existingUsersCount(): int
    {
        try {
            return Schema::hasTable('users') ? DB::table('users')->count() : 0;
        } catch (\Throwable) {
            return 0;
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
}
