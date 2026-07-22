<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchModuleSetting;
use App\Models\BranchUserAccess;
use App\Models\User;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(ModuleBranchService $branches)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $branches->ensureMainBranch();
        $branches->backfillManagerAccess();

        return view('developer.branches.index', [
            'branches' => Branch::query()->orderByDesc('is_main')->orderBy('name')->get(),
            'moduleLabels' => $branches->moduleLabels(),
        ]);
    }

    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        return view('developer.branches.create');
    }

    public function store(Request $request, ModuleBranchService $branches): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $validated = $this->validateBranch($request);
        if ($request->hasFile('logo')) {
            $validated['logo_path'] = $request->file('logo')->store('branch-logos', 'public');
        }

        $branch = $branches->createBranch($validated);

        return redirect()->route('developer.branches.show', $branch)->with('success', 'Branch created.');
    }

    public function show(Branch $branch, ModuleBranchService $branches)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $branch->is_main
            ? $branches->ensureRegistryModuleSettings($branch)
            : $branches->ensureBranchModuleRows($branch);

        return view('developer.branches.show', $this->branchViewData($branch));
    }

    public function edit(Branch $branch, ModuleBranchService $branches)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $branch->is_main
            ? $branches->ensureRegistryModuleSettings($branch)
            : $branches->ensureBranchModuleRows($branch);

        return view('developer.branches.edit', $this->branchViewData($branch));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $validated = $this->validateBranch($request, $branch);

        if ($branch->is_main) {
            $validated['status'] = 'active';
            $validated['code'] = 'MAIN';
        } elseif (blank($validated['code'] ?? null)) {
            $validated['code'] = $branch->code;
        }

        if ($request->hasFile('logo')) {
            if ($branch->logo_path) {
                Storage::disk('public')->delete($branch->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('branch-logos', 'public');
        }

        $branch->update($validated);

        return redirect()->route('developer.branches.show', $branch)->with('success', 'Branch saved.');
    }

    public function updateStatus(Request $request, Branch $branch): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $validated = $request->validate([
            'status' => ['required', 'in:active,inactive'],
        ]);

        if ($branch->is_main && $validated['status'] !== 'active') {
            return redirect()->route('developer.branches.show', $branch)
                ->with('error', 'Main Branch cannot be deactivated.');
        }

        $branch->update(['status' => $validated['status']]);

        return redirect()->route('developer.branches.show', $branch)->with('success', 'Branch status updated.');
    }

    protected function validateBranch(Request $request, ?Branch $branch = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('branches', 'code')->ignore($branch?->id)],
            'prefix' => ['required', 'string', 'max:20', Rule::unique('branches', 'prefix')->ignore($branch?->id)],
            'display_name' => ['nullable', 'string', 'max:160'],
            'owner_name' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'ntn_cnic' => ['nullable', 'string', 'max:80'],
            'strn_sntn' => ['nullable', 'string', 'max:80'],
            'header_text' => ['nullable', 'string', 'max:160'],
            'footer_text' => ['nullable', 'string', 'max:160'],
            'terms_text' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,inactive'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $validated['prefix'] = strtoupper(preg_replace('/[^A-Z0-9]+/', '', $validated['prefix'] ?? '')) ?: 'BR';

        return $validated;
    }

    public function updateModules(Request $request, ModuleBranchService $branches): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        if ($request->filled('modules_payload')) {
            $decodedModules = json_decode((string) $request->input('modules_payload'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedModules)) {
                $request->merge(['modules' => $decodedModules]);
            }
        }

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'modules_payload' => ['nullable', 'string'],
            'modules' => ['array'],
            'modules.*._present' => ['nullable', 'boolean'],
            'modules.*._advanced_present' => ['nullable', 'boolean'],
            'modules.*.branch_enabled' => ['nullable', 'boolean'],
            'modules.*.allow_user_switching' => ['nullable', 'boolean'],
            'modules.*.supports_multi_branch_selector' => ['nullable', 'boolean'],
            'modules.*.record_filtering_enabled' => ['nullable', 'boolean'],
            'modules.*.has_branch_id_support' => ['nullable', 'boolean'],
            'modules.*.supports_branch_branding' => ['nullable', 'boolean'],
            'modules.*.supports_branch_serial_prefix' => ['nullable', 'boolean'],
            'modules.*.supports_doc_identity_prefix' => ['nullable', 'boolean'],
            'modules.*.default_order_discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'modules.*.discount_disabled' => ['nullable', 'boolean'],
            'modules.*.document_note' => ['nullable', 'string', 'max:300'],
            'modules.*.doc_identity_prefix' => ['nullable', 'string', 'max:20'],
            'modules.*.default_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'modules.*.status' => ['nullable', 'in:active,inactive'],
        ]);

        $branchId = $validated['branch_id'] ?? null;
        $branch = $branchId ? Branch::query()->find($branchId) : null;
        foreach (($validated['modules'] ?? []) as $moduleKey => $data) {
            $moduleKey = $branches->canonicalModuleKey((string) $moduleKey);
            if (!array_key_exists($moduleKey, $branches->moduleRegistry())) {
                continue;
            }
            $module = $branches->moduleRegistry()[$moduleKey];
            $multiBranchEnabled = (bool) ($data['supports_multi_branch_selector'] ?? false);
            $switchingEnabled = (bool) ($data['allow_user_switching'] ?? false) || $multiBranchEnabled;
            $branchEnabled = (bool) ($data['branch_enabled'] ?? false) || $switchingEnabled;
            $advancedPresent = array_key_exists('_advanced_present', $data);
            $setting = BranchModuleSetting::query()
                ->where('branch_id', $branchId)
                ->where('module_key', $moduleKey)
                ->first();
            $metadata = is_array($setting?->metadata) ? $setting->metadata : [];
            $metadata['record_filtering_enabled'] = (bool) ($data['record_filtering_enabled'] ?? false);

            $metadata['branchable'] = $switchingEnabled;
            $metadata['supports_branch_selector'] = $switchingEnabled;
            $metadata['supports_record_filtering'] = (bool) ($data['record_filtering_enabled'] ?? false);
            $metadata['can_filter_records'] = (bool) ($data['record_filtering_enabled'] ?? false);
            $metadata['supports_multi_branch_selector'] = $multiBranchEnabled;

            foreach ([
                'has_branch_id_support',
                'supports_branch_branding',
                'supports_branch_serial_prefix',
                'supports_doc_identity_prefix',
            ] as $key) {
                if ($advancedPresent || array_key_exists($key, $data)) {
                    $metadata[$key] = (bool) ($data[$key] ?? false);
                }
            }

            if ($advancedPresent || array_key_exists('supports_branch_branding', $data)) {
                $metadata['can_use_branch_branding'] = (bool) ($data['supports_branch_branding'] ?? false);
            }

            if ($advancedPresent || array_key_exists('supports_branch_serial_prefix', $data)) {
                $metadata['supports_serial_prefix'] = (bool) ($data['supports_branch_serial_prefix'] ?? false);
            }

            $metadata['is_system_module'] = ! $switchingEnabled;
            if (array_key_exists('doc_identity_prefix', $data)) {
                $metadata['doc_identity_prefix'] = strtoupper(preg_replace('/[^A-Z0-9]+/', '', (string) $data['doc_identity_prefix']));
            }

            if ($moduleKey === 'orders' && array_key_exists('default_order_discount_percent', $data)) {
                $metadata['default_order_discount_percent'] = max(
                    0,
                    min(100, (int) $data['default_order_discount_percent'])
                );
            }
            if (in_array($moduleKey, ['orders', 'invoices'], true)) {
                if ($advancedPresent || array_key_exists('discount_disabled', $data)) {
                    $metadata['discount_disabled'] = (bool) ($data['discount_disabled'] ?? false);
                }
                if (array_key_exists('document_note', $data)) {
                    $metadata['document_note'] = trim((string) $data['document_note']);
                }
            } else {
                unset($metadata['discount_disabled'], $metadata['document_note']);
            }

            $values = [
                'branch_enabled' => $branchEnabled,
                'allow_user_switching' => $switchingEnabled,
                'default_branch_id' => $data['default_branch_id'] ?? null,
                'status' => $data['status'] ?? $setting?->status ?? 'active',
                'metadata' => $metadata,
            ];

            foreach (array_unique([$branchId, $branch?->is_main ? null : $branchId], SORT_REGULAR) as $targetBranchId) {
                BranchModuleSetting::query()->updateOrCreate(
                    ['branch_id' => $targetBranchId, 'module_key' => $moduleKey],
                    $values,
                );
            }
        }

        return redirect()->back()->with('success', 'Module branch settings saved.');
    }

    public function updateAccess(Request $request, ModuleBranchService $branches): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        if (filled($request->input('module_key'))) {
            $request->merge(['module_key' => $branches->canonicalModuleKey((string) $request->input('module_key'))]);
        }

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'role' => ['required', 'string', Rule::in(['developer', 'owner', 'admin', 'manager', 'accountant', 'store_keeper', 'guest', 'supplier'])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'module_key' => ['nullable', 'string', Rule::in(array_keys($branches->moduleRegistry()))],
            'can_view' => ['nullable', 'boolean'],
            'can_create' => ['nullable', 'boolean'],
            'can_update' => ['nullable', 'boolean'],
            'can_delete' => ['nullable', 'boolean'],
            'can_switch' => ['nullable', 'boolean'],
            'can_manage' => ['nullable', 'boolean'],
        ]);
        $validated['module_key'] = filled($validated['module_key'] ?? null)
            ? $branches->canonicalModuleKey((string) $validated['module_key'])
            : null;

        BranchUserAccess::query()->updateOrCreate(
            [
                'branch_id' => $validated['branch_id'],
                'role' => $validated['user_id'] ?? null ? null : $validated['role'],
                'user_id' => $validated['user_id'] ?? null,
                'module_key' => $validated['module_key'] ?? null,
            ],
            [
                'user_id' => null,
                'can_view' => $request->boolean('can_view'),
                'can_create' => $request->boolean('can_create'),
                'can_update' => $request->boolean('can_update'),
                'can_delete' => $request->boolean('can_delete'),
                'can_switch' => $request->boolean('can_switch'),
                'can_manage' => $request->boolean('can_manage'),
            ],
        );

        return redirect()->back()->with('success', 'Branch access saved.');
    }

    protected function branchViewData(Branch $branch): array
    {
        $branches = app(ModuleBranchService::class);
        $registry = $branches->moduleRegistry();

        return [
            'branch' => $branch->load(['moduleSettings', 'accessRows']),
            'branches' => Branch::query()->orderByDesc('is_main')->orderBy('name')->get(),
            'moduleLabels' => $branches->moduleLabels(),
            'moduleRegistry' => $registry,
            'moduleSettings' => BranchModuleSetting::query()
                ->where('branch_id', $branch->id)
                ->get()
                ->sortBy(fn (BranchModuleSetting $setting) => ($registry[$branches->canonicalModuleKey($setting->module_key)]['group'] ?? 'ZZZ') . '|' . ($registry[$branches->canonicalModuleKey($setting->module_key)]['label'] ?? $setting->module_key))
                ->values()
                ->keyBy(fn (BranchModuleSetting $setting) => $branches->canonicalModuleKey($setting->module_key)),
            'accessRows' => BranchUserAccess::query()
                ->with(['branch', 'user'])
                ->where('branch_id', $branch->id)
                ->orderBy('role')
                ->orderBy('user_id')
                ->orderBy('module_key')
                ->get(),
            'roleLabels' => ['developer', 'owner', 'admin', 'manager', 'accountant', 'store_keeper', 'guest', 'supplier'],
            'users' => User::query()->select('id', 'name', 'username', 'role')->orderBy('name')->get(),
        ];
    }
}
