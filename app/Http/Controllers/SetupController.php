<?php

namespace App\Http\Controllers;

use App\Models\Setup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SetupController extends Controller
{
    public function index(Request $request) {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        if ($request->ajax()) {
            $setups = Setup::orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $setups, 'authLayout' => 'table']);
        }

        return view('setups.index');
    }
    public function create()
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };
        
        return view('setups.add');
    }
    public function store(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };
        
        // Validation rules
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255|unique:setups,title',
            'short_title' => 'nullable|string|max:255|unique:setups,short_title',
            'type' => 'required|string|max:255',
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
