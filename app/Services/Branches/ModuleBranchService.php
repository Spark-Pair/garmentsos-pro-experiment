<?php

namespace App\Services\Branches;

use App\Models\Branch;
use App\Models\BranchModuleSetting;
use App\Models\BranchUserAccess;
use App\Models\User;
use App\Models\UserModuleBranchPreference;
use App\Services\Settings\BrandingSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ModuleBranchService
{
    public const MODULE_REGISTRY = [
        'dashboard' => [
            'label' => 'Dashboard',
            'group' => 'General',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Global summary screen. Branch filtering can be added later when dashboard totals are reviewed.',
        ],
        'articles' => [
            'label' => 'Articles',
            'group' => 'Inventory',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => true,
            'notes' => 'Safe for branch switching and record filtering.',
        ],
        'article_parts' => [
            'label' => 'Article Parts',
            'group' => 'Inventory',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Shared article configuration. Keep global until parts ownership is reviewed.',
        ],
        'customers' => [
            'label' => 'Customers',
            'group' => 'Parties',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Customer master data is global by default. When enabled, customer lists and dependent dropdowns follow the selected branch.',
            'dependencies' => 'Use with Orders, Invoices, Customer Payments, Statements, and Reports for branch-wise customer activity.',
        ],
        'suppliers' => [
            'label' => 'Suppliers',
            'group' => 'Parties',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Supplier master data is global by default. When enabled, supplier lists and dependent dropdowns follow the selected branch.',
            'dependencies' => 'Use with Purchases, Supplier Payments, Stock, Statements, and Reports when supplier flows are branch-scoped.',
        ],
        'employees' => [
            'label' => 'Employees / Workers',
            'group' => 'People',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Shared worker records by default. Enable branch filtering only if workers should be branch-specific.',
        ],
        'users' => [
            'label' => 'Users',
            'group' => 'People',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'User accounts remain global by default. Branch filtering affects user management lists only, not login.',
        ],
        'orders' => [
            'label' => 'Orders',
            'group' => 'Sales',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => false,
            'notes' => 'Enable with invoices and customer payments so sales totals remain consistent.',
            'dependencies' => 'Recommended group: Articles, Orders, Invoices, Physical Quantities, Customer Payments, Statements, Reports, Productions.',
        ],
        'invoices' => [
            'label' => 'Invoices',
            'group' => 'Sales',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => false,
            'notes' => 'Financial module. Enable with related orders, payments, statements, and reports.',
            'dependencies' => 'Recommended group: Articles, Orders, Invoices, Customer Payments, Statements, Reports.',
        ],
        'vouchers' => [
            'label' => 'Vouchers',
            'group' => 'Finance',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => true,
            'notes' => 'Safe for branch switching and document branding.',
        ],
        'customer_payments' => [
            'label' => 'Customer Payments',
            'group' => 'Finance',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Financial module. Customer records remain global; payment rows can be branch-scoped.',
            'dependencies' => 'Recommended group: Orders, Invoices, Customer Payments, Statements, Reports.',
        ],
        'supplier_payments' => [
            'label' => 'Supplier Payments',
            'group' => 'Finance',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Financial module. Enable with purchases and supplier reports/statements.',
            'dependencies' => 'Recommended group: Suppliers, Purchases, Supplier Payments, Stock, Reports.',
        ],
        'purchases' => [
            'label' => 'Purchases',
            'group' => 'Purchases',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => false,
            'notes' => 'Enable with supplier payments and stock reports when purchase flows are branch-wise.',
            'dependencies' => 'Recommended group: Suppliers, Purchases, Supplier Payments, Physical Quantities, Reports.',
        ],
        'productions' => [
            'label' => 'Productions',
            'group' => 'Production',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => true,
            'notes' => 'Production tickets and issue/receive lists are branch-aware.',
        ],
        'physical_quantities' => [
            'label' => 'Physical Quantities / Stock',
            'group' => 'Inventory',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Inventory module. Enable with sales/purchase modules that affect stock.',
            'dependencies' => 'Recommended group: Articles, Orders, Invoices, Productions, Purchases, Reports.',
        ],
        'reports' => [
            'label' => 'Reports',
            'group' => 'Reports',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => true,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Reports can be branch-filtered when the underlying transaction modules are enabled.',
            'dependencies' => 'Recommended with Statements, Pending Payments, Invoices, Orders, Physical Quantities.',
        ],
        'statements' => [
            'label' => 'Statements',
            'group' => 'Reports',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => true,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Statements support one branch, multiple branches, or all branches while customers remain global.',
            'dependencies' => 'Recommended with Invoices and Customer Payments for branch-aware customer balances.',
        ],
        'pending_payments' => [
            'label' => 'Pending Payments',
            'group' => 'Reports',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => true,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Pending payment reports can filter branch-wise customer payment rows.',
            'dependencies' => 'Recommended with Customer Payments, Statements, and Reports.',
        ],
        'settings' => [
            'label' => 'Settings',
            'group' => 'Administration',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Developer/global configuration, not branch-scoped.',
        ],
        'stock' => [
            'label' => 'Stock',
            'group' => 'Inventory',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Computed stock view. Branch scope is controlled by Physical Quantities and sales/purchase modules.',
        ],
        'bank_accounts' => [
            'label' => 'Bank Accounts',
            'group' => 'Finance',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Bank accounts are global finance setup by default.',
        ],
        'bank_transactions' => [
            'label' => 'Bank Transactions / Daily Ledger',
            'group' => 'Finance',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Ledger references remain global until branch ownership is reviewed.',
        ],
        'cargo' => [
            'label' => 'Cargo',
            'group' => 'Dispatch',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => false,
            'notes' => 'Cargo lists can be made branch-wise after cargo flow review.',
        ],
        'shipments' => [
            'label' => 'Shipments',
            'group' => 'Dispatch',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => false,
            'notes' => 'Shipment documents can be branch-wise after dispatch flow review.',
        ],
        'sales_returns' => [
            'label' => 'Sales Returns',
            'group' => 'Sales',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => false,
            'notes' => 'Sales return rows should follow invoice branch when enabled.',
        ],
        'expenses' => [
            'label' => 'Expenses / Fabrics',
            'group' => 'Purchases',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => false,
            'notes' => 'Expense/fabric reference numbers can be branch-wise after purchase flow review.',
        ],
        'developer_settings' => [
            'label' => 'Developer Settings',
            'group' => 'Developer',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'System configuration, not branch-scoped.',
        ],
        'branches' => [
            'label' => 'Branches',
            'group' => 'Developer',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Branch management controls branch behavior and remains global.',
        ],
        'backups' => [
            'label' => 'Backups / Restore',
            'group' => 'Developer',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Backup and restore are installation-level tools.',
        ],
        'license' => [
            'label' => 'License',
            'group' => 'Developer',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'License/device state is installation-wide, not branch-scoped.',
        ],
    ];

    public const MODULES = [
        'dashboard' => 'Dashboard',
        'articles' => 'Articles',
        'article_parts' => 'Article Parts',
        'customers' => 'Customers',
        'suppliers' => 'Suppliers',
        'employees' => 'Employees / Workers',
        'users' => 'Users',
        'orders' => 'Orders',
        'invoices' => 'Invoices',
        'vouchers' => 'Vouchers',
        'customer_payments' => 'Customer Payments',
        'supplier_payments' => 'Supplier Payments',
        'purchases' => 'Purchases',
        'productions' => 'Productions',
        'physical_quantities' => 'Physical Quantities / Stock',
        'reports' => 'Reports',
        'statements' => 'Statements',
        'pending_payments' => 'Pending Payments',
        'settings' => 'Settings',
        'stock' => 'Stock',
        'bank_accounts' => 'Bank Accounts',
        'bank_transactions' => 'Bank Transactions / Daily Ledger',
        'cargo' => 'Cargo',
        'shipments' => 'Shipments',
        'sales_returns' => 'Sales Returns',
        'expenses' => 'Expenses / Fabrics',
        'developer_settings' => 'Developer Settings',
        'branches' => 'Branches',
        'backups' => 'Backups / Restore',
        'license' => 'License',
    ];

    public function moduleRegistry(): array
    {
        return self::MODULE_REGISTRY;
    }

    public function moduleConfig(string $moduleKey): ?array
    {
        return self::MODULE_REGISTRY[$moduleKey] ?? null;
    }

    public function isRegisteredModule(string $moduleKey): bool
    {
        return array_key_exists($moduleKey, self::MODULE_REGISTRY);
    }

    public function ensureMainBranch(array $details = []): Branch
    {
        $branch = Branch::query()->where('is_main', true)->first();
        if ($branch) {
            $this->ensureGlobalModuleRows($branch);
            return $branch;
        }

        return DB::transaction(function () use ($details): Branch {
            $name = trim((string) ($details['name'] ?? $details['company_name'] ?? 'Main Branch'));
            $branch = Branch::query()->create([
                'name' => $name !== '' ? $name : 'Main Branch',
                'code' => 'MAIN',
                'prefix' => 'MAIN',
                'status' => 'active',
                'logo_path' => $details['logo_path'] ?? null,
                'display_name' => $details['display_name'] ?? $name,
                'owner_name' => $details['owner_name'] ?? null,
                'header_text' => $details['header_text'] ?? $name,
                'footer_text' => $details['footer_text'] ?? null,
                'terms_text' => $details['terms_text'] ?? null,
                'phone' => $details['phone'] ?? null,
                'email' => $details['email'] ?? null,
                'address' => $details['address'] ?? null,
                'city' => $details['city'] ?? null,
                'province' => $details['province'] ?? null,
                'ntn_cnic' => $details['ntn_cnic'] ?? null,
                'strn_sntn' => $details['strn_sntn'] ?? null,
                'is_main' => true,
                'metadata' => ['created_by' => 'setup'],
            ]);

            foreach (self::MODULES as $moduleKey => $label) {
                $globalKey = $this->moduleSettingsHaveBranchId()
                    ? ['branch_id' => null, 'module_key' => $moduleKey]
                    : ['module_key' => $moduleKey];

                BranchModuleSetting::query()->firstOrCreate($globalKey, [
                    'branch_enabled' => (bool) (self::MODULE_REGISTRY[$moduleKey]['safe_default_enabled'] ?? false),
                    'default_branch_id' => $branch->id,
                    'allow_user_switching' => (bool) (self::MODULE_REGISTRY[$moduleKey]['safe_default_enabled'] ?? false),
                    'status' => 'active',
                    'metadata' => ['label' => $label],
                ]);

                if ($this->moduleSettingsHaveBranchId()) {
                    BranchModuleSetting::query()->firstOrCreate(
                        ['branch_id' => $branch->id, 'module_key' => $moduleKey],
                        [
                            'branch_enabled' => (bool) (self::MODULE_REGISTRY[$moduleKey]['safe_default_enabled'] ?? false),
                            'default_branch_id' => $branch->id,
                            'allow_user_switching' => (bool) (self::MODULE_REGISTRY[$moduleKey]['safe_default_enabled'] ?? false),
                            'status' => 'active',
                            'metadata' => ['label' => $label, 'main_branch_default' => true],
                        ],
                    );
                }
            }

            foreach (['developer', 'owner', 'admin', 'manager', 'accountant', 'store_keeper', 'guest', 'supplier'] as $role) {
                BranchUserAccess::query()->firstOrCreate(
                    [
                        'branch_id' => $branch->id,
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
                    ],
                );
            }

            return $branch;
        });
    }

    public function ensureGlobalModuleRows(?Branch $mainBranch = null): void
    {
        if (!Schema::hasTable('branch_module_settings')) {
            return;
        }

        $mainBranch ??= Schema::hasTable('branches')
            ? Branch::query()->where('is_main', true)->first()
            : null;

        foreach (self::MODULES as $moduleKey => $label) {
            $safeDefault = (bool) (self::MODULE_REGISTRY[$moduleKey]['safe_default_enabled'] ?? false);
            $globalKey = $this->moduleSettingsHaveBranchId()
                ? ['branch_id' => null, 'module_key' => $moduleKey]
                : ['module_key' => $moduleKey];

            BranchModuleSetting::query()->firstOrCreate($globalKey, [
                'branch_enabled' => $safeDefault,
                'default_branch_id' => $mainBranch?->id,
                'allow_user_switching' => $safeDefault,
                'status' => 'active',
                'metadata' => ['label' => $label, 'global_registry_repair' => true],
            ]);
        }
    }

    public function setting(string $moduleKey): ?BranchModuleSetting
    {
        if (!Schema::hasTable('branch_module_settings')) {
            return null;
        }

        $query = BranchModuleSetting::query()->where('module_key', $moduleKey);
        if ($this->moduleSettingsHaveBranchId()) {
            $query->whereNull('branch_id');
        }

        $setting = $query->first() ?? BranchModuleSetting::query()->where('module_key', $moduleKey)->first();
        if (!$setting && array_key_exists($moduleKey, self::MODULE_REGISTRY)) {
            $this->ensureGlobalModuleRows();

            $query = BranchModuleSetting::query()->where('module_key', $moduleKey);
            if ($this->moduleSettingsHaveBranchId()) {
                $query->whereNull('branch_id');
            }

            $setting = $query->first() ?? BranchModuleSetting::query()->where('module_key', $moduleKey)->first();
        }

        return $setting;
    }

    public function branchSetting(string $moduleKey, ?int $branchId): ?BranchModuleSetting
    {
        if (!$branchId || !Schema::hasTable('branch_module_settings') || !$this->moduleSettingsHaveBranchId()) {
            return null;
        }

        return BranchModuleSetting::query()
            ->where('branch_id', $branchId)
            ->where('module_key', $moduleKey)
            ->first();
    }

    public function isEnabled(string $moduleKey): bool
    {
        if (!$this->isRegisteredModule($moduleKey) || !Schema::hasTable('branch_module_settings')) {
            return false;
        }

        $setting = $this->setting($moduleKey);
        if ($setting?->branch_enabled && $setting->status === 'active') {
            return true;
        }

        if (!$this->moduleSettingsHaveBranchId()) {
            return false;
        }

        return BranchModuleSetting::query()
            ->where('module_key', $moduleKey)
            ->whereNotNull('branch_id')
            ->where('branch_enabled', true)
            ->where('status', 'active')
            ->exists();
    }

    public function isBranchEnabled(string $moduleKey, int $branchId): bool
    {
        if (!$this->isRegisteredModule($moduleKey) || !Schema::hasTable('branch_module_settings')) {
            return false;
        }

        $setting = $this->branchSetting($moduleKey, $branchId);

        if ($setting) {
            return (bool) ($setting->branch_enabled && $setting->status === 'active');
        }

        $global = $this->setting($moduleKey);

        return (bool) ($global?->branch_enabled && $global->status === 'active');
    }

    public function branchAllowsSwitching(string $moduleKey, int $branchId): bool
    {
        if (!$this->isRegisteredModule($moduleKey) || !$this->isBranchEnabled($moduleKey, $branchId)) {
            return false;
        }

        $setting = $this->branchSetting($moduleKey, $branchId);

        return $setting
            ? (bool) $setting->allow_user_switching
            : (bool) $this->setting($moduleKey)?->allow_user_switching;
    }

    public function selectedBranch(string $moduleKey, ?User $user = null): ?Branch
    {
        if (!Schema::hasTable('branches')) {
            return null;
        }

        $user ??= Auth::user();
        $setting = $this->setting($moduleKey);
        $defaultBranchId = $setting?->default_branch_id;

        if (!$this->isEnabled($moduleKey) || !$user) {
            return $defaultBranchId
                ? Branch::query()->find($defaultBranchId)
                : Branch::query()->where('is_main', true)->first();
        }

        $preferred = UserModuleBranchPreference::query()
            ->where('user_id', $user->id)
            ->where('module_key', $moduleKey)
            ->first();

        if ($preferred && $this->isBranchEnabled($moduleKey, $preferred->branch_id) && $this->canView($preferred->branch_id, $moduleKey, $user)) {
            return Branch::query()->find($preferred->branch_id);
        }

        $branch = $defaultBranchId
            ? Branch::query()->find($defaultBranchId)
            : Branch::query()->where('is_main', true)->first();

        if ($branch && $this->isBranchEnabled($moduleKey, $branch->id) && $this->canView($branch->id, $moduleKey, $user)) {
            return $branch;
        }

        return Branch::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->first(fn (Branch $candidate) => $this->isBranchEnabled($moduleKey, $candidate->id) && $this->canView($candidate->id, $moduleKey, $user));
    }

    public function switchableBranches(string $moduleKey, ?User $user = null)
    {
        return $this->availableBranchesForModule($moduleKey, $user);
    }

    public function availableBranchesForModule(string $moduleKey, ?User $user = null)
    {
        $user ??= Auth::user();
        if (!$user || !$this->isRegisteredModule($moduleKey) || !($this->moduleConfig($moduleKey)['branchable'] ?? false)) {
            return collect();
        }

        if ($this->canManageBranches($user)) {
            return Branch::query()
                ->where('status', 'active')
                ->orderByDesc('is_main')
                ->orderBy('name')
                ->get()
                ->filter(fn (Branch $branch) => $this->branchAllowsSwitching($moduleKey, $branch->id))
                ->values();
        }

        $branchIds = BranchUserAccess::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('role', $user->role);
            })
            ->where(function (Builder $query) use ($moduleKey) {
                $query->whereNull('module_key')->orWhere('module_key', $moduleKey);
            })
            ->where('can_view', true)
            ->where('can_switch', true)
            ->pluck('branch_id')
            ->unique();

        return Branch::query()
            ->whereIn('id', $branchIds)
            ->where('status', 'active')
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get()
            ->filter(fn (Branch $branch) => $this->branchAllowsSwitching($moduleKey, $branch->id))
            ->values();
    }

    public function selectedBranchForModule(string $moduleKey, ?User $user = null): ?Branch
    {
        return $this->selectedBranch($moduleKey, $user);
    }

    public function selectedBranchIdForModule(string $moduleKey, ?User $user = null): ?int
    {
        return $this->selectedBranchForModule($moduleKey, $user)?->id;
    }

    public function isModuleBranchEnabled(string $moduleKey, ?int $branchId = null): bool
    {
        return $branchId ? $this->isBranchEnabled($moduleKey, $branchId) : $this->isEnabled($moduleKey);
    }

    public function shouldFilterRecords(string $moduleKey, ?User $user = null): bool
    {
        $user ??= Auth::user();

        return (bool) ($user && $this->isEnabled($moduleKey) && ($this->moduleConfig($moduleKey)['can_filter_records'] ?? false));
    }

    public function isModuleBranchSwitchable(string $moduleKey, ?User $user = null): bool
    {
        return $this->canShowSelector($moduleKey, $user);
    }

    public function canShowSelector(string $moduleKey, ?User $user = null): bool
    {
        $user ??= Auth::user();
        if (!$user || !$this->isRegisteredModule($moduleKey)) {
            return false;
        }

        $config = $this->moduleConfig($moduleKey);
        if (!($config['branchable'] ?? false)) {
            return false;
        }

        return $this->availableBranchesForModule($moduleKey, $user)->count() > 1;
    }

    public function supportsMultiBranchSelector(string $moduleKey): bool
    {
        return (bool) ($this->moduleConfig($moduleKey)['supports_multi_branch_selector'] ?? false);
    }

    public function selectedBranchIdsForModule(string $moduleKey, ?User $user = null): array
    {
        $user ??= Auth::user();
        $available = $this->availableBranchesForModule($moduleKey, $user);
        if (!$user || $available->isEmpty()) {
            return [];
        }

        if (!$this->supportsMultiBranchSelector($moduleKey)) {
            return array_values(array_filter([(int) ($this->selectedBranchIdForModule($moduleKey, $user) ?? 0)]));
        }

        $availableIds = $available->pluck('id')->map(fn ($id) => (int) $id)->values();
        $preferred = UserModuleBranchPreference::query()
            ->where('user_id', $user->id)
            ->where('module_key', $moduleKey)
            ->first();

        $storedIds = collect($preferred?->branch_ids ?? [])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->intersect($availableIds)
            ->values();

        return $storedIds->isNotEmpty()
            ? $storedIds->all()
            : $availableIds->all();
    }

    public function selectedBranchSummaryForModule(string $moduleKey, ?User $user = null): string
    {
        $available = $this->availableBranchesForModule($moduleKey, $user);
        $selectedIds = $this->selectedBranchIdsForModule($moduleKey, $user);
        if ($available->isEmpty() || $selectedIds === []) {
            return $this->selectedBranchForModule($moduleKey, $user)?->name ?? 'Select Branch';
        }

        if (count($selectedIds) === $available->count()) {
            return 'All Branches';
        }

        return $available
            ->whereIn('id', $selectedIds)
            ->pluck('name')
            ->implode(' + ');
    }

    public function setPreference(string $moduleKey, int $branchId, User $user): void
    {
        if (!$this->isRegisteredModule($moduleKey) || !($this->moduleConfig($moduleKey)['branchable'] ?? false)) {
            abort(422, 'Unknown or non-branchable module.');
        }

        if (!$this->isEnabled($moduleKey)) {
            abort(422, 'This module does not support branch switching.');
        }

        if (!$this->canSwitch($branchId, $moduleKey, $user)) {
            abort(403, 'You cannot switch to this branch for this module.');
        }

        UserModuleBranchPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'module_key' => $moduleKey],
            [
                'branch_id' => $branchId,
                'selection_mode' => 'single',
                'branch_ids' => [$branchId],
            ],
        );
    }

    public function setMultiPreference(string $moduleKey, array $branchIds, User $user): void
    {
        if (!$this->supportsMultiBranchSelector($moduleKey)) {
            abort(422, 'This module does not support multi-branch selection.');
        }

        if (!$this->isEnabled($moduleKey)) {
            abort(422, 'This module does not support branch switching.');
        }

        $availableIds = $this->availableBranchesForModule($moduleKey, $user)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $selectedIds = collect($branchIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->intersect($availableIds)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            $selectedIds = $availableIds;
        }

        if ($selectedIds->isEmpty()) {
            abort(422, 'No accessible branches are available for this module.');
        }

        UserModuleBranchPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'module_key' => $moduleKey],
            [
                'branch_id' => $selectedIds->first(),
                'selection_mode' => 'multiple',
                'branch_ids' => $selectedIds->all(),
            ],
        );
    }

    public function applyScope(Builder $query, string $moduleKey): Builder
    {
        if (!$this->shouldFilterRecords($moduleKey)) {
            return $query;
        }

        $model = $query->getModel();
        if (!Schema::hasColumn($model->getTable(), 'branch_id')) {
            return $query;
        }

        $branch = $this->selectedBranch($moduleKey);

        return $branch
            ? $query->where($model->getTable() . '.branch_id', $branch->id)
            : $query->whereRaw('1 = 0');
    }

    public function applyRelatedScope(Builder $query, string $relatedModuleKey, string $workingModuleKey): Builder
    {
        if (!$this->shouldFilterRecords($relatedModuleKey)) {
            return $query;
        }

        $model = $query->getModel();
        if (!Schema::hasColumn($model->getTable(), 'branch_id')) {
            return $query;
        }

        $branchId = $this->selectedBranchIdForModule($workingModuleKey)
            ?: $this->selectedBranchIdForModule($relatedModuleKey);

        return $branchId
            ? $query->where($model->getTable() . '.branch_id', $branchId)
            : $query->whereRaw('1 = 0');
    }

    public function branchIdForCreate(string $moduleKey): ?int
    {
        return $this->selectedBranch($moduleKey)?->id;
    }

    public function canSwitch(int $branchId, string $moduleKey, User $user): bool
    {
        if (!$this->branchAllowsSwitching($moduleKey, $branchId)) {
            return false;
        }

        return $this->canManageBranches($user) || BranchUserAccess::query()
            ->where('branch_id', $branchId)
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)->orWhere('role', $user->role);
            })
            ->where(function (Builder $query) use ($moduleKey) {
                $query->whereNull('module_key')->orWhere('module_key', $moduleKey);
            })
            ->where('can_view', true)
            ->where('can_switch', true)
            ->exists();
    }

    public function canView(int $branchId, string $moduleKey, User $user): bool
    {
        if (!$this->isBranchEnabled($moduleKey, $branchId)) {
            return false;
        }

        return $this->canManageBranches($user) || BranchUserAccess::query()
            ->where('branch_id', $branchId)
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)->orWhere('role', $user->role);
            })
            ->where(function (Builder $query) use ($moduleKey) {
                $query->whereNull('module_key')->orWhere('module_key', $moduleKey);
            })
            ->where('can_view', true)
            ->exists();
    }

    public function canManageBranches(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user && in_array($user->role, ['developer', 'owner', 'admin'], true);
    }

    public function createBranch(array $data): Branch
    {
        return DB::transaction(function () use ($data): Branch {
            $code = strtoupper(preg_replace('/[^A-Z0-9]+/', '-', Str::ascii($data['code'] ?? $data['name'])));
            $code = trim($code, '-') ?: 'BRANCH';
            $baseCode = $code;
            $suffix = 2;
            while (Branch::query()->where('code', $code)->exists()) {
                $code = $baseCode . '-' . $suffix;
                $suffix++;
            }

            $branch = Branch::query()->create([
                'name' => $data['name'],
                'code' => $code,
                'prefix' => $data['prefix'] ?? $code,
                'status' => $data['status'] ?? 'active',
                'phone' => $data['phone'] ?? null,
                'display_name' => $data['display_name'] ?? $data['name'],
                'owner_name' => $data['owner_name'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'province' => $data['province'] ?? null,
                'ntn_cnic' => $data['ntn_cnic'] ?? null,
                'strn_sntn' => $data['strn_sntn'] ?? null,
                'header_text' => $data['header_text'] ?? null,
                'footer_text' => $data['footer_text'] ?? null,
                'terms_text' => $data['terms_text'] ?? null,
                'logo_path' => $data['logo_path'] ?? null,
                'is_main' => false,
                'metadata' => ['created_from' => 'developer_ui'],
            ]);

            $this->grantManagerAccess($branch);
            $this->ensureBranchModuleRows($branch);

            return $branch;
        });
    }

    public function ensureBranchModuleRows(Branch $branch): void
    {
        foreach (self::MODULES as $moduleKey => $label) {
            if (!$this->moduleSettingsHaveBranchId()) {
                return;
            }

            $global = $this->setting($moduleKey);
            $safeDefault = (bool) (self::MODULE_REGISTRY[$moduleKey]['safe_default_enabled'] ?? false);
            BranchModuleSetting::query()->firstOrCreate(
                ['branch_id' => $branch->id, 'module_key' => $moduleKey],
                [
                    'branch_enabled' => (bool) ($global?->branch_enabled ?? $safeDefault),
                    'default_branch_id' => $global?->default_branch_id,
                    'allow_user_switching' => (bool) ($global?->allow_user_switching ?? $safeDefault),
                    'status' => $global?->status ?? 'active',
                    'metadata' => ['label' => $label, 'created_for_branch' => true],
                ],
            );
        }
    }

    public function grantManagerAccess(Branch $branch): void
    {
        foreach (['developer', 'owner', 'admin'] as $role) {
            BranchUserAccess::query()->updateOrCreate(
                [
                    'branch_id' => $branch->id,
                    'role' => $role,
                    'module_key' => null,
                ],
                [
                    'user_id' => null,
                    'can_view' => true,
                    'can_create' => true,
                    'can_update' => true,
                    'can_delete' => $role === 'developer',
                    'can_switch' => true,
                    'can_manage' => true,
                ],
            );
        }
    }

    public function backfillManagerAccess(): void
    {
        if (!Schema::hasTable('branches') || !Schema::hasTable('branch_user_access')) {
            return;
        }

        $this->ensureGlobalModuleRows();

        Branch::query()
            ->where(function (Builder $query) {
                $query->where('status', 'active')->orWhere('is_main', true);
            })
            ->get()
            ->each(function (Branch $branch) {
                $this->grantManagerAccess($branch);
                $this->ensureBranchModuleRows($branch);
            });
    }

    protected function moduleSettingsHaveBranchId(): bool
    {
        return Schema::hasTable('branch_module_settings')
            && Schema::hasColumn('branch_module_settings', 'branch_id');
    }

    public function mainBranch(): ?Branch
    {
        if (!Schema::hasTable('branches')) {
            return null;
        }

        return Branch::query()->where('is_main', true)->first()
            ?? Branch::query()->orderBy('id')->first();
    }

    public function syncMainBranchDetails(array $details): void
    {
        $branch = $this->ensureMainBranch($details);
        $updates = [];

        foreach ([
            'display_name',
            'owner_name',
            'logo_path',
            'phone',
            'email',
            'address',
            'city',
            'province',
            'ntn_cnic',
            'strn_sntn',
            'header_text',
            'footer_text',
            'terms_text',
        ] as $field) {
            $value = $details[$field] ?? ($field === 'display_name' ? ($details['company_name'] ?? $details['name'] ?? null) : null);
            if (blank($branch->{$field}) && filled($value)) {
                $updates[$field] = $value;
            }
        }

        if ($updates !== []) {
            $branch->update($updates);
        }
    }

    public function branchForRecord(object $record, string $moduleKey): ?Branch
    {
        if (isset($record->branch) && $record->branch instanceof Branch) {
            return $record->branch;
        }

        $branchId = data_get($record, 'branch_id');
        if ($branchId && Schema::hasTable('branches')) {
            return Branch::query()->find($branchId);
        }

        return $this->selectedBranch($moduleKey) ?: $this->mainBranch();
    }

    public function documentBranding(string $moduleKey, ?object $record = null): array
    {
        $branch = $record ? $this->branchForRecord($record, $moduleKey) : $this->selectedBranch($moduleKey);
        $branch ??= $this->mainBranch();
        $app = app(BrandingSettingsService::class)->clientCompany();
        $branchLogoUrl = null;

        if ($branch?->logo_path) {
            $publicLogo = public_path('storage/' . ltrim($branch->logo_path, '/'));
            $branchLogoUrl = is_file($publicLogo)
                ? asset('storage/' . ltrim($branch->logo_path, '/'))
                : route('branch-logos.show', $branch);
        }

        return [
            'source' => $branch ? 'branch' : 'app_settings',
            'branch_id' => $branch?->id,
            'name' => $branch?->displayName() ?: ($app->name ?? config('app.name')),
            'display_name' => $branch?->displayName() ?: ($app->name ?? config('app.name')),
            'owner_name' => $branch?->owner_name,
            'phone' => $branch?->phone ?: ($app->phone_number ?? ''),
            'phone_number' => $branch?->phone ?: ($app->phone_number ?? ''),
            'email' => $branch?->email,
            'address' => $branch?->address,
            'city' => $branch?->city,
            'province' => $branch?->province,
            'ntn_cnic' => $branch?->ntn_cnic,
            'strn_sntn' => $branch?->strn_sntn,
            'header_text' => $branch?->header_text,
            'footer_text' => $branch?->footer_text,
            'terms_text' => $branch?->terms_text,
            'logo' => $branch?->logo_path ?: ($app->logo ?? null),
            'logo_text' => $branch?->header_text ?: ($app->logo_text ?? null),
            'logo_url' => $branchLogoUrl ?: (!empty($app->logo) ? asset('images/' . $app->logo) : null),
        ];
    }
}
