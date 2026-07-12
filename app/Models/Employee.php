<?php

namespace App\Models;

use App\Traits\EmployeeComputed;
use App\Traits\Filterable;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    use Filterable, EmployeeComputed;

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        "category",
        "branch_id",
        "type_id",
        "employee_name",
        "urdu_title",
        "phone_number",
        "joining_date",
        "cnic_no",
        "salary",
        'status',
        'profile_picture',
    ];

    protected $casts = [
        'joining_date' => 'date',
    ];

    protected $appends = ['balance'];

    public function type() {
        return $this->belongsTo(Setup::class, 'type_id');
    }

    public function tags() {
        return $this->hasMany(IssuedFabric::class, 'worker_id');
    }

    public function productions() {
        return $this->hasMany(Production::class, 'worker_id');
    }

    public function salaries() {
        return $this->hasMany(Salary::class, 'employee_id');
    }

    public function attendance() {
        return $this->hasMany(Attendance::class, 'employee_id');
    }

    public function payments() {
        return $this->hasMany(EmployeePayment::class, 'employee_id');
    }

    public function supplier() {
        return $this->hasOne(Supplier::class, 'worker_id');
    }

    public function getBalanceAttribute()
    {
        return $this->calculateBalance();
    }

    public function calculateBalance($fromDate = null, $toDate = null, $formatted = false, $includeGivenDate = true)
    {
        $productionsQuery = $this->productions();
        $paymentsQuery = $this->payments();
        $salariesQuery = $this->salaries(); // 👈 new line

        DateRange::apply($productionsQuery, 'date', $fromDate, $toDate, $includeGivenDate);
        DateRange::apply($paymentsQuery, 'date', $fromDate, $toDate, $includeGivenDate);
        DateRange::apply($salariesQuery, 'date', $fromDate, $toDate, $includeGivenDate);

        // Calculate totals
        $totalProductions = $productionsQuery->sum('netAmount') ?? 0;
        $totalPayments = $paymentsQuery->sum('amount') ?? 0;
        $totalSalaries = $salariesQuery->sum('amount') ?? 0; // 👈 added

        // Final balance (production - payments - salary)
        $balance = ($totalProductions + $totalSalaries) - $totalPayments;

        return $formatted ? \App\Support\Money::format($balance) : $balance;
    }
}
