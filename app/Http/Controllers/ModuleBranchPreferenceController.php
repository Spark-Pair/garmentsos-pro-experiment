<?php

namespace App\Http\Controllers;

use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ModuleBranchPreferenceController extends Controller
{
    public function store(Request $request, ModuleBranchService $branches): RedirectResponse
    {
        $validated = $request->validate([
            'module_key' => ['required', 'string', 'max:80', Rule::in(array_keys($branches->moduleRegistry()))],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'selection_mode' => ['nullable', 'string', Rule::in(['single', 'multiple'])],
            'redirect_to' => ['nullable', 'string', 'max:2048'],
        ]);

        if (($validated['selection_mode'] ?? 'single') === 'multiple') {
            $branches->setMultiPreference($validated['module_key'], $validated['branch_ids'] ?? [], $request->user());
        } else {
            $request->validate(['branch_id' => ['required', 'integer', 'exists:branches,id']]);
            $branches->setPreference($validated['module_key'], (int) $validated['branch_id'], $request->user());
        }

        $redirectTo = $validated['redirect_to'] ?? null;
        if (
            is_string($redirectTo)
            && (str_starts_with($redirectTo, url('/')) || str_starts_with($redirectTo, $request->getSchemeAndHttpHost()))
        ) {
            return redirect()->to($redirectTo)->with('success', 'Branch selection updated for this module.');
        }

        return redirect()->back()->with('success', 'Branch selection updated for this module.');
    }
}
