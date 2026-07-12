<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->addBranchBrandingFields();
        $this->rebuildBranchModuleSettings();
        $mainBranchId = $this->ensureMainBranch();
        $this->copySettingsToMainBranch($mainBranchId);
        $this->seedGlobalModuleSettings($mainBranchId);
        $this->seedPerBranchModuleSettings();
        $this->seedManagerAccess();
        $this->backfillBranchIds($mainBranchId);
    }

    public function down(): void
    {
        // Keep the expanded branch identity schema on rollback. Dropping these
        // columns would risk losing client document branding configuration.
    }

    private function addBranchBrandingFields(): void
    {
        if (!Schema::hasTable('branches')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            foreach ([
                'display_name' => fn () => $table->string('display_name')->nullable()->after('logo_path'),
                'owner_name' => fn () => $table->string('owner_name')->nullable()->after('display_name'),
                'email' => fn () => $table->string('email')->nullable()->after('phone'),
                'province' => fn () => $table->string('province')->nullable()->after('city'),
                'ntn_cnic' => fn () => $table->string('ntn_cnic')->nullable()->after('province'),
                'strn_sntn' => fn () => $table->string('strn_sntn')->nullable()->after('ntn_cnic'),
                'terms_text' => fn () => $table->text('terms_text')->nullable()->after('footer_text'),
            ] as $column => $definition) {
                if (!Schema::hasColumn('branches', $column)) {
                    $definition();
                }
            }
        });
    }

    private function rebuildBranchModuleSettings(): void
    {
        if (!Schema::hasTable('branch_module_settings')) {
            return;
        }

        $existing = DB::table('branch_module_settings')->get();
        Schema::dropIfExists('branch_module_settings_next');

        Schema::create('branch_module_settings_next', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('module_key');
            $table->boolean('branch_enabled')->default(false);
            $table->foreignId('default_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->boolean('allow_user_switching')->default(false);
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'module_key']);
            $table->index(['module_key', 'status']);
        });

        foreach ($existing as $row) {
            DB::table('branch_module_settings_next')->insert([
                'branch_id' => property_exists($row, 'branch_id') ? $row->branch_id : null,
                'module_key' => $row->module_key,
                'branch_enabled' => (bool) $row->branch_enabled,
                'default_branch_id' => $row->default_branch_id,
                'allow_user_switching' => (bool) $row->allow_user_switching,
                'status' => $row->status ?? 'active',
                'metadata' => $row->metadata ?? null,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => now(),
            ]);
        }

        Schema::drop('branch_module_settings');
        Schema::rename('branch_module_settings_next', 'branch_module_settings');
    }

    private function ensureMainBranch(): int
    {
        if (!Schema::hasTable('branches')) {
            return 0;
        }

        $existing = DB::table('branches')->where('is_main', true)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('branches')->insertGetId([
            'name' => $this->settingValue('company_name') ?? 'Main Branch',
            'code' => 'MAIN',
            'status' => 'active',
            'is_main' => true,
            'metadata' => json_encode(['created_by' => 'branch_identity_hardening']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function copySettingsToMainBranch(int $mainBranchId): void
    {
        if ($mainBranchId <= 0) {
            return;
        }

        $updates = [
            'display_name' => $this->settingValue('company_name') ?? $this->settingValue('client_name'),
            'phone' => $this->settingValue('phone'),
            'address' => $this->settingValue('setup_company_address') ?? $this->settingValue('address'),
            'header_text' => $this->settingValue('print_header_text') ?? $this->settingValue('company_name'),
            'footer_text' => $this->settingValue('print_footer_text'),
            'updated_at' => now(),
        ];

        $current = DB::table('branches')->where('id', $mainBranchId)->first();
        foreach ($updates as $key => $value) {
            if ($key === 'updated_at') {
                continue;
            }

            if ($value === null || trim((string) $value) === '' || filled(data_get($current, $key))) {
                unset($updates[$key]);
            }
        }

        if (count($updates) > 1) {
            DB::table('branches')->where('id', $mainBranchId)->update($updates);
        }
    }

    private function seedPerBranchModuleSettings(): void
    {
        if (!Schema::hasTable('branches') || !Schema::hasTable('branch_module_settings')) {
            return;
        }

        $globalRows = DB::table('branch_module_settings')->whereNull('branch_id')->get()->keyBy('module_key');
        foreach (DB::table('branches')->get() as $branch) {
            foreach ($this->moduleKeys() as $moduleKey) {
                $global = $globalRows->get($moduleKey);
                DB::table('branch_module_settings')->updateOrInsert(
                    ['branch_id' => $branch->id, 'module_key' => $moduleKey],
                    [
                        'branch_enabled' => $global ? (bool) $global->branch_enabled : $this->safeDefaultEnabled($moduleKey),
                        'default_branch_id' => $global?->default_branch_id,
                        'allow_user_switching' => $global ? (bool) $global->allow_user_switching : $this->safeDefaultEnabled($moduleKey),
                        'status' => $global->status ?? 'active',
                        'metadata' => json_encode(['seeded_per_branch' => true]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }

    private function safeDefaultEnabled(string $moduleKey): bool
    {
        return in_array($moduleKey, ['articles', 'vouchers', 'productions'], true);
    }

    private function seedGlobalModuleSettings(int $mainBranchId): void
    {
        if ($mainBranchId <= 0 || !Schema::hasTable('branch_module_settings')) {
            return;
        }

        foreach ($this->moduleKeys() as $moduleKey) {
            DB::table('branch_module_settings')->updateOrInsert(
                ['branch_id' => null, 'module_key' => $moduleKey],
                [
                    'branch_enabled' => $this->safeDefaultEnabled($moduleKey),
                    'default_branch_id' => $mainBranchId,
                    'allow_user_switching' => $this->safeDefaultEnabled($moduleKey),
                    'status' => 'active',
                    'metadata' => json_encode(['seeded_global_registry' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function moduleKeys(): array
    {
        return [
            'dashboard',
            'articles',
            'article_parts',
            'customers',
            'suppliers',
            'employees',
            'users',
            'orders',
            'invoices',
            'vouchers',
            'customer_payments',
            'supplier_payments',
            'purchases',
            'productions',
            'physical_quantities',
            'reports',
            'statements',
            'pending_payments',
            'settings',
        ];
    }

    private function seedManagerAccess(): void
    {
        if (!Schema::hasTable('branches') || !Schema::hasTable('branch_user_access')) {
            return;
        }

        foreach (DB::table('branches')->where('status', 'active')->orWhere('is_main', true)->get() as $branch) {
            foreach (['developer', 'owner', 'admin'] as $role) {
                DB::table('branch_user_access')->updateOrInsert(
                    ['branch_id' => $branch->id, 'role' => $role, 'module_key' => null],
                    [
                        'can_view' => true,
                        'can_create' => true,
                        'can_update' => true,
                        'can_delete' => $role === 'developer',
                        'can_switch' => true,
                        'can_manage' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }

    private function backfillBranchIds(int $mainBranchId): void
    {
        if ($mainBranchId <= 0) {
            return;
        }

        foreach (['articles', 'customers', 'suppliers', 'employees', 'users', 'physical_quantities', 'orders', 'invoices', 'customer_payments', 'supplier_payments', 'purchases', 'vouchers', 'productions'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            if (!Schema::hasColumn($tableName, 'branch_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
                });
            }

            DB::table($tableName)->whereNull('branch_id')->update(['branch_id' => $mainBranchId]);
        }
    }

    private function settingValue(string $key): ?string
    {
        if (Schema::hasTable('branding_settings')) {
            $value = DB::table('branding_settings')->where('key', $key)->value('value');
            if (is_string($value) && trim($value) !== '') {
                return Str::limit(trim($value), 190, '');
            }
        }

        if (Schema::hasTable('app_settings')) {
            $value = DB::table('app_settings')->where('key', $key)->value('value');
            if (is_string($value) && trim($value) !== '') {
                return Str::limit(trim($value), 190, '');
            }
        }

        return null;
    }
};
