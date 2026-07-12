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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('status')->default('active');
            $table->string('logo_path')->nullable();
            $table->string('header_text')->nullable();
            $table->string('footer_text')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_main')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_module_settings', function (Blueprint $table) {
            $table->id();
            $table->string('module_key')->unique();
            $table->boolean('branch_enabled')->default(false);
            $table->foreignId('default_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->boolean('allow_user_switching')->default(false);
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_user_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->string('module_key')->nullable();
            $table->boolean('can_view')->default(true);
            $table->boolean('can_create')->default(true);
            $table->boolean('can_update')->default(true);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_switch')->default(false);
            $table->boolean('can_manage')->default(false);
            $table->timestamps();
            $table->index(['branch_id', 'module_key']);
            $table->index(['user_id', 'module_key']);
            $table->index(['role', 'module_key']);
        });

        Schema::create('user_module_branch_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('module_key');
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'module_key']);
        });

        $mainBranchId = $this->ensureMainBranch();
        $this->seedModuleSettings($mainBranchId);
        $this->seedMainBranchAccess($mainBranchId);
        $this->addBranchIds($mainBranchId);
    }

    public function down(): void
    {
        foreach ($this->branchableTables() as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('branch_id');
                });
            }
        }

        Schema::dropIfExists('user_module_branch_preferences');
        Schema::dropIfExists('branch_user_access');
        Schema::dropIfExists('branch_module_settings');
        Schema::dropIfExists('branches');
    }

    private function ensureMainBranch(): int
    {
        $now = now();
        $companyName = $this->settingValue('branding.company_name')
            ?? $this->settingValue('company_name')
            ?? $this->settingValue('client_name')
            ?? 'Main Branch';

        $existing = DB::table('branches')->where('is_main', true)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('branches')->insertGetId([
            'name' => $companyName,
            'code' => 'MAIN',
            'status' => 'active',
            'phone' => $this->settingValue('branding.phone') ?? $this->settingValue('phone'),
            'address' => $this->settingValue('setup_company_address') ?? $this->settingValue('branding.address'),
            'is_main' => true,
            'metadata' => json_encode(['created_by' => 'module_wise_branch_migration']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedModuleSettings(int $mainBranchId): void
    {
        $now = now();
        foreach ($this->moduleSettings() as $moduleKey => $enabled) {
            DB::table('branch_module_settings')->updateOrInsert(
                ['module_key' => $moduleKey],
                [
                    'branch_enabled' => $enabled,
                    'default_branch_id' => $mainBranchId,
                    'allow_user_switching' => $enabled,
                    'status' => 'active',
                    'metadata' => json_encode(['initial_branch_foundation' => true]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    private function seedMainBranchAccess(int $mainBranchId): void
    {
        $now = now();
        foreach (['developer', 'owner', 'admin', 'manager', 'accountant', 'store_keeper', 'guest', 'supplier'] as $role) {
            DB::table('branch_user_access')->updateOrInsert(
                [
                    'branch_id' => $mainBranchId,
                    'role' => $role,
                    'module_key' => null,
                ],
                [
                    'can_view' => true,
                    'can_create' => true,
                    'can_update' => !in_array($role, ['guest', 'supplier'], true),
                    'can_delete' => $role === 'developer',
                    'can_switch' => in_array($role, ['developer', 'owner', 'admin'], true),
                    'can_manage' => in_array($role, ['developer', 'owner', 'admin'], true),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    private function addBranchIds(int $mainBranchId): void
    {
        foreach ($this->branchableTables() as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
            });

            DB::table($table)->whereNull('branch_id')->update(['branch_id' => $mainBranchId]);
        }
    }

    private function branchableTables(): array
    {
        return [
            'articles',
            'customers',
            'suppliers',
            'employees',
            'users',
            'physical_quantities',
            'orders',
            'invoices',
            'customer_payments',
            'supplier_payments',
            'purchases',
            'vouchers',
            'productions',
        ];
    }

    private function moduleSettings(): array
    {
        return [
            'dashboard' => false,
            'articles' => true,
            'article_parts' => false,
            'customers' => false,
            'suppliers' => false,
            'employees' => false,
            'users' => false,
            'orders' => false,
            'invoices' => false,
            'vouchers' => true,
            'customer_payments' => false,
            'supplier_payments' => false,
            'purchases' => false,
            'productions' => true,
            'physical_quantities' => false,
            'reports' => false,
            'statements' => false,
            'pending_payments' => false,
            'settings' => false,
        ];
    }

    private function settingValue(string $key): ?string
    {
        if (Schema::hasTable('app_settings')) {
            $value = DB::table('app_settings')->where('key', $key)->value('value');
            if (is_string($value) && trim($value) !== '') {
                return Str::limit(trim($value), 190, '');
            }
        }

        if (Schema::hasTable('branding_settings')) {
            $shortKey = Str::after($key, 'branding.');
            $value = DB::table('branding_settings')->where('key', $shortKey)->value('value');
            if (is_string($value) && trim($value) !== '') {
                return Str::limit(trim($value), 190, '');
            }
        }

        return null;
    }
};
