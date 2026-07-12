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
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
            return $resp;
        }

        $branches->ensureMainBranch();
        $branches->backfillManagerAccess();

        return view('developer.branches.index', [
            'branches' => Branch::query()->orderByDesc('is_main')->orderBy('name')->get(),
            'moduleLabels' => ModuleBranchService::MODULES,
        ]);
    }

    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
            return $resp;
        }

        return view('developer.branches.create');
    }

    public function store(Request $request, ModuleBranchService $branches): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
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
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
            return $resp;
        }

        $branches->ensureBranchModuleRows($branch);

        return view('developer.branches.show', $this->branchViewData($branch));
    }

    public function edit(Branch $branch, ModuleBranchService $branches)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
            return $resp;
        }

        $branches->ensureBranchModuleRows($branch);

        return view('developer.branches.edit', $this->branchViewData($branch));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
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
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
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

    public function updateModules(Request $request): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
            return $resp;
        }

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'modules' => ['array'],
            'modules.*.branch_enabled' => ['nullable', 'boolean'],
            'modules.*.allow_user_switching' => ['nullable', 'boolean'],
            'modules.*.default_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'modules.*.status' => ['nullable', 'in:active,inactive'],
        ]);

        $branchId = $validated['branch_id'] ?? null;
        foreach (($validated['modules'] ?? []) as $moduleKey => $data) {
            BranchModuleSetting::query()->updateOrCreate(
                ['branch_id' => $branchId, 'module_key' => $moduleKey],
                [
                    'branch_enabled' => (bool) ($data['branch_enabled'] ?? false),
                    'allow_user_switching' => (bool) ($data['allow_user_switching'] ?? false),
                    'default_branch_id' => $data['default_branch_id'] ?? null,
                    'status' => $data['status'] ?? 'active',
                ],
            );
        }

        return redirect()->back()->with('success', 'Module branch settings saved.');
    }

    public function updateAccess(Request $request): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
            return $resp;
        }

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'role' => ['required', 'string', Rule::in(['developer', 'owner', 'admin', 'manager', 'accountant', 'store_keeper', 'guest', 'supplier'])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'module_key' => ['nullable', 'string', Rule::in(array_keys(ModuleBranchService::MODULES))],
            'can_view' => ['nullable', 'boolean'],
            'can_create' => ['nullable', 'boolean'],
            'can_update' => ['nullable', 'boolean'],
            'can_delete' => ['nullable', 'boolean'],
            'can_switch' => ['nullable', 'boolean'],
            'can_manage' => ['nullable', 'boolean'],
        ]);

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
        return [
            'branch' => $branch->load(['moduleSettings', 'accessRows']),
            'branches' => Branch::query()->orderByDesc('is_main')->orderBy('name')->get(),
            'moduleLabels' => ModuleBranchService::MODULES,
            'moduleRegistry' => ModuleBranchService::MODULE_REGISTRY,
            'moduleSettings' => BranchModuleSetting::query()
                ->where('branch_id', $branch->id)
                ->orderBy('module_key')
                ->get()
                ->keyBy('module_key'),
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
