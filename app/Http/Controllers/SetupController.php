<?php

namespace App\Http\Controllers;

use App\Models\Setup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SetupController extends Controller
{
    public function index(Request $request) {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            $setups = Setup::orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $setups, 'authLayout' => $authLayout]);
        }

        return view('setups.index', compact('authLayout'));
    }
    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $existingShortTitles = Setup::query()
            ->whereNotNull('short_title')
            ->pluck('short_title')
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->values();

        $titlesByType = Setup::query()
            ->select('type', 'title')
            ->get()
            ->groupBy('type')
            ->map(fn ($items) => $items
                ->pluck('title')
                ->map(fn ($value) => strtolower(trim((string) $value)))
                ->filter()
                ->values())
            ->toArray();

        return view('setups.add', compact('existingShortTitles', 'titlesByType'));
    }
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $request->merge([
            'title' => trim((string) $request->input('title')),
            'short_title' => trim((string) $request->input('short_title')),
            'type' => trim((string) $request->input('type')),
        ]);
        
        // Validation rules
        $validator = Validator::make($request->all(), [
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('setups', 'title')->where(fn ($query) => $query->where('type', $request->type)),
            ],
            'short_title' => 'nullable|string|max:255|unique:setups,short_title',
            'type' => 'required|string|max:255',
        ], [
            'title.unique' => 'Is type mein yeh title pehle se mojood hai.',
            'short_title.unique' => 'Yeh short title pehle se system mein use ho raha hai. Short title globally unique hona chahiye.',
        ]);

        // If validation fails, return with errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        
        Setup::create([
            'title' => $request->title,
            'short_title' => $request->short_title,
            'type' => $request->type,
        ]);

        return redirect()->back()->with('success', 'Setup added successfully');
    }
}
