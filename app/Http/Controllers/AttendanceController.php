<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Salary;
use App\Services\Branches\ModuleBranchService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        return view('attendances.record');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        $attendances = collect(json_decode($request->attendances, true));

        $validAttendances = collect();
        $invalidEmployeeNames = collect();

        // Step 1-3 combined: filter, deduplicate per employee per date, map employee_id
        $attendances
            ->filter(fn($item) => $item['state'] === "C/In")
            ->sortBy('datetime')
            ->groupBy(fn($item) => \Carbon\Carbon::createFromFormat('d-M-y g:i A', $item['datetime'])->toDateString() . '_' . $item['employee_name'])
            ->each(function ($group) use ($validAttendances, $invalidEmployeeNames) {
                $item = $group->first(); // first record per employee per date
                $employee = app(ModuleBranchService::class)
                    ->applyRelatedScope(Employee::where('employee_name', $item['employee_name']), 'employees', 'attendances')
                    ->first();

                if ($employee) {
                    $validAttendances->push(app(ModuleBranchService::class)->assignBranchOnCreate([
                        'employee_id' => $employee->id,
                        'datetime'    => Carbon::createFromFormat('d-M-y g:i A', $item['datetime'])->format('Y-m-d H:i:s'),
                        'state'       => $item['state'],
                    ], 'attendances'));
                } else {
                    $invalidEmployeeNames->push($item['employee_name']);
                }
            });

        // Remove duplicates from invalid employee names
        $invalidEmployeeNames = $invalidEmployeeNames->unique()->values()->toArray();

        // Step 4: Upsert valid attendances
        Attendance::upsert(
            $validAttendances->toArray(),
            ['employee_id', 'datetime'],
            ['state']
        );

        // Step 5: Redirect back with invalid employee names
        return redirect()->back()->with('invalid_employees', $invalidEmployeeNames);
    }

    public function manageSalary()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        $employee_options = [];
        $employees = app(ModuleBranchService::class)
            ->applyRelatedScope(Employee::where('status', 'active')->whereNotNull('salary')->with('type'), 'employees', 'attendances')
            ->get();

        foreach($employees as $employee) {
            $employee_options[(int)$employee->id] = [
                'text' => ucfirst($employee->employee_name) . ' | ' . $employee->type->title,
                'data_option' => $employee,
            ];
        }

        return view('attendances.manage-salary', compact('employee_options'));
    }

    public function manageSalaryPost(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        $request->validate([
            'month' => ['required', 'date_format:Y-m', Rule::unique('salaries')->where(function ($query) use ($request) {
                return $query->where('employee_id', $request->employee_id);
            }),],
            'employee_id' => 'required|integer|exists:employees,id',
            'types_array' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        $employee = app(ModuleBranchService::class)->applyRelatedScope(Employee::query(), 'employees', 'attendances')->find($request->employee_id);
        if (!$employee) {
            return redirect()->back()->withErrors(['employee_id' => 'Selected employee is not available for this branch.'])->withInput();
        }

        Salary::create(app(ModuleBranchService::class)->assignBranchOnCreate([
            'month' => $request->month,
            'employee_id' => $request->employee_id,
            'types_array' => json_decode($request->types_array ?? '[]'),
            'amount' => $request->amount,
        ], 'attendances'));

        return redirect()->back()->with('success', 'Salary added successfuly.');
    }

    public function generateSlip()
    {
        return view('attendances.generate-slip');
    }

    public function generateSlipPost(Request $request)
    {
        $month = Carbon::parse($request->month . '-01');
        $currentMonth = $month->month;
        $currentYear = $month->year;

        $attendances = app(ModuleBranchService::class)->applyScope(Attendance::whereMonth('datetime', $currentMonth), 'attendances')
            ->whereYear('datetime', $currentYear)
            ->get()
            ->groupBy('employee_id');

        $employees = app(ModuleBranchService::class)
            ->applyRelatedScope(Employee::whereIn('id', $attendances->keys()), 'employees', 'attendances')
            ->get()
            ->keyBy('id');

        // Generate full month dates
        $start = Carbon::create($currentYear, $currentMonth, 1);
        $end = $start->copy()->endOfMonth();
        $dates = collect();
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates->push($date->format('Y-m-d'));
        }

        $data = [];
        foreach ($attendances as $empId => $records) {
            $emp = $employees[$empId];
            $data[] = [
                'employee_name' => $emp->employee_name,
                'month' => $month->format('F - Y'),
                'records' => $dates->map(function ($d) use ($records) {
                    $record = $records->first(function ($r) use ($d) {
                        return Carbon::parse($r->datetime)->format('Y-m-d') === $d;
                    });
                    return [
                        'date' => $d,
                        'time' => $record ? Carbon::parse($record->datetime)->format('H:i A') : '-'
                    ];
                }),
            ];
        }

        return response()->json($data);
    }
}
