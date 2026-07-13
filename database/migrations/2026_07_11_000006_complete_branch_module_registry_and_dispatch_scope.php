<?php

use App\Services\Branches\BranchModuleRegistryService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('branches')) {
            return;
        }

        $mainBranchId = $this->mainBranchId();

        $this->seedModuleRegistry($mainBranchId);
    }

    public function down(): void
    {
        //
    }

    private function mainBranchId(): ?int
    {
        return DB::table('branches')->where('is_main', true)->value('id')
            ?: DB::table('branches')->orderBy('id')->value('id');
    }

    private function seedModuleRegistry(?int $mainBranchId): void
    {
        if (!$mainBranchId || !Schema::hasTable('branch_module_settings')) {
            return;
        }

        $hasBranchId = Schema::hasColumn('branch_module_settings', 'branch_id');
        $branches = DB::table('branches')->pluck('id');
        $now = now();

        foreach (app(BranchModuleRegistryService::class)->registry() as $moduleKey => $config) {
            $safeDefault = (bool) ($config['safe_default_enabled'] ?? false);
            $metadata = json_encode([
                'label' => $config['label'] ?? $moduleKey,
                'group' => $config['group'] ?? 'General',
                'registry_repair' => true,
            ]);

            $globalKey = $hasBranchId
                ? ['branch_id' => null, 'module_key' => $moduleKey]
                : ['module_key' => $moduleKey];

            $this->insertMissingModuleSetting($globalKey, [
                'branch_enabled' => $safeDefault,
                'default_branch_id' => $mainBranchId,
                'allow_user_switching' => $safeDefault,
                'status' => 'active',
                'metadata' => $metadata,
                'created_at' => $now,
                'updated_at' => $now,
            ], $hasBranchId);

            if (!$hasBranchId) {
                continue;
            }

            foreach ($branches as $branchId) {
                $isMainBranch = (int) $branchId === (int) $mainBranchId;
                $this->insertMissingModuleSetting(
                    ['branch_id' => $branchId, 'module_key' => $moduleKey],
                    [
                        'branch_enabled' => $isMainBranch ? true : $safeDefault,
                        'default_branch_id' => $mainBranchId,
                        'allow_user_switching' => $isMainBranch ? true : $safeDefault,
                        'status' => 'active',
                        'metadata' => $metadata,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $hasBranchId,
                );
            }
        }
    }

    private function insertMissingModuleSetting(array $key, array $values, bool $hasBranchId): void
    {
        $query = DB::table('branch_module_settings')->where('module_key', $key['module_key']);

        if ($hasBranchId) {
            isset($key['branch_id'])
                ? $query->where('branch_id', $key['branch_id'])
                : $query->whereNull('branch_id');
        }

        if ($query->exists()) {
            return;
        }

        DB::table('branch_module_settings')->insert($key + $values);
    }

};
