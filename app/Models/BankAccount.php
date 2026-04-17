<?php

namespace App\Models;

use App\Traits\BankAccountComputed;
use App\Traits\Filterable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class BankAccount extends Model
{
    use HasFactory;

    use Filterable, BankAccountComputed;

    protected $fillable = ['category', 'sub_category', 'bank_id', 'account_title', 'date', 'remarks', 'account_no', 'chqbk_serial_start', 'chqbk_serial_end', 'status'];

    protected $hidden = [
        'bank_id',
        'creator_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected $appends = ['balance', 'available_cheques'];

    protected static function booted()
    {
        // Automatically set creator_id when creating a new Article
        static::creating(function ($thisModel) {
            if (Auth::check()) {
                $thisModel->creator_id = Auth::id();
            }
        });

        // Always eager load the associated creator
        static::addGlobalScope('withCreator', function (Builder $builder) {
            $builder->with('creator');
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }

    public function subCategory()
    {
        return $this->morphTo();
    }
    public function bank()
    {
        return $this->belongsTo(Setup::class, 'bank_id')->where('type', 'bank_name');
    }
    public function paymentPrograms()
    {
        return $this->morphMany(PaymentProgram::class, 'sub_category');
    }
    public function bankAccounts()
    {
        return $this->hasOne(BankAccount::class, 'id');
    }

    public function statementAdjustments()
    {
        return $this->morphMany(StatementAdjustment::class, 'adjustable');
    }

    public function getAvailableChequesAttribute()
    {
        if ($this->category !== 'self') {
            return null;
        }

        // Ensure serial start and end are valid numbers
        $start = (int) $this->chqbk_serial_start;
        $end = (int) $this->chqbk_serial_end;

        if ($start <= 0 || $end <= 0 || $end < $start) {
            // Invalid or missing serials → return empty array
            return [];
        }

        // Get all the used cheques for this bank account
        $usedCheques = SupplierPayment::where('bank_account_id', $this->id)
            ->pluck('cheque_no')
            ->toArray();

        // Generate full range of cheque numbers
        $fullRange = range($start, $end);

        // Filter out the used ones
        $available = array_diff($fullRange, $usedCheques);

        // Return available cheque numbers
        return array_values($available);
    }

    public function getBalanceAttribute()
    {
        return $this->calculateBalance();
    }

    public function calculateBalance($fromDate = null, $toDate = null, $formatted = false, $includeGivenDate = true)
    {
        $balance = 0;
        $adjustmentsQuery = $this->statementAdjustments();
        if ($this->category === 'self') {
            // Self category: existing logic
            $customerPayments = CustomerPayment::where('bank_account_id', $this->id);
            // Keep self-account balance in sync with the statement view by
            // counting every supplier payment linked to this bank account,
            // including CR entries that may not have a voucher_id.
            $supplierPayments = SupplierPayment::where('bank_account_id', $this->id);

            // Apply date filters
            if ($fromDate && $toDate) {
                if ($includeGivenDate) {
                    $customerPayments->whereBetween('date', [$fromDate, $toDate]);
                    $supplierPayments->whereBetween('date', [$fromDate, $toDate]);
                    $adjustmentsQuery->whereBetween('date', [$fromDate, $toDate]);
                } else {
                    $customerPayments->where('date', '>', $fromDate)->where('date', '<', $toDate);
                    $supplierPayments->where('date', '>', $fromDate)->where('date', '<', $toDate);
                    $adjustmentsQuery->where('date', '>', $fromDate)->where('date', '<', $toDate);
                }
            } elseif ($fromDate) {
                $operator = $includeGivenDate ? '>=' : '>';
                $customerPayments->where('date', $operator, $fromDate);
                $supplierPayments->where('date', $operator, $fromDate);
                $adjustmentsQuery->where('date', $operator, $fromDate);
            } elseif ($toDate) {
                $operator = $includeGivenDate ? '<=' : '<';
                $customerPayments->where('date', $operator, $toDate);
                $supplierPayments->where('date', $operator, $toDate);
                $adjustmentsQuery->where('date', $operator, $toDate);
            }

            $totalPayments = $customerPayments->sum('amount') ?? 0;
            $totalPays = $supplierPayments->sum('amount') ?? 0;
            $adjustmentsNet = (float) $adjustmentsQuery->get()->sum(fn($adjustment) => (float) $adjustment->net_amount);

            $balance = ($totalPayments - $totalPays) + $adjustmentsNet;

        } else if ($this->category === 'supplier') {
            $clearBalance = PaymentClear::where('bank_account_id', $this->id)
                ->where('method', '!=', 'cash') // ignore cash
                ->when($fromDate, fn($q) => $q->where('clear_date', $includeGivenDate ? '>=' : '>', $fromDate))
                ->when($toDate, fn($q) => $q->where('clear_date', $includeGivenDate ? '<=' : '<', $toDate))
                ->sum('amount');
            $adjustmentsNet = (float) $adjustmentsQuery
                ->when($fromDate, fn($q) => $q->where('date', $includeGivenDate ? '>=' : '>', $fromDate))
                ->when($toDate, fn($q) => $q->where('date', $includeGivenDate ? '<=' : '<', $toDate))
                ->get()
                ->sum(fn($adjustment) => (float) $adjustment->net_amount);
            $balance = $clearBalance + $adjustmentsNet;
        } else if ($this->category === 'customer') {
            $clearBalance = PaymentClear::where('bank_account_id', $this->id)
                ->where('method', '!=', 'cash') // ignore cash
                ->when($fromDate, fn($q) => $q->where('clear_date', $includeGivenDate ? '>=' : '>', $fromDate))
                ->when($toDate, fn($q) => $q->where('clear_date', $includeGivenDate ? '<=' : '<', $toDate))
                ->sum('amount');
            $adjustmentsNet = (float) $adjustmentsQuery
                ->when($fromDate, fn($q) => $q->where('date', $includeGivenDate ? '>=' : '>', $fromDate))
                ->when($toDate, fn($q) => $q->where('date', $includeGivenDate ? '<=' : '<', $toDate))
                ->get()
                ->sum(fn($adjustment) => (float) $adjustment->net_amount);
            $balance = $clearBalance + $adjustmentsNet;
        }

        return $formatted ? number_format($balance, 1, '.', ',') : $balance;
    }
    public function getStatement($fromDate, $toDate, $type = 'general')
    {
        $type = $type ?: 'general';
        // 🧮 Opening & Closing Balances
        $openingBalance = $this->calculateBalance(null, $fromDate, false, false);
        $periodBalance  = $this->calculateBalance($fromDate, $toDate);
        $closingBalance = $openingBalance + $periodBalance;

        // --- Shared Queries ---
        $customerQuery = CustomerPayment::where('bank_account_id', $this->id)
            ->whereBetween('date', [$fromDate, $toDate]);

        $supplierQuery = SupplierPayment::where('bank_account_id', $this->id)
            ->whereBetween('date', [$fromDate, $toDate])
            ->with('bankAccount.bank');
        $adjustments = $this->statementAdjustments()
            ->whereBetween('date', [$fromDate, $toDate])
            ->get();
        $formatAdjustment = function ($adjustment, $forSummary = false) {
            $isPlus = $adjustment->direction === 'plus';
            $label = $adjustment->entry_type === 'opening_balance' ? 'Opening Balance' : 'Adjustment';

            return [
                'date' => $forSummary ? Carbon::parse($adjustment->date)->toDateString() : $adjustment->date,
                'reff_no' => ($adjustment->entry_type === 'opening_balance' ? 'OB' : 'ADJ') . '-' . $adjustment->id,
                'type' => $isPlus ? 'invoice' : 'payment',
                'method' => $label,
                'bill' => $isPlus ? (float) $adjustment->amount : 0,
                'payment' => $isPlus ? 0 : (float) $adjustment->amount,
                'description' => $adjustment->remarks ?: $label,
                'created_at' => $adjustment->created_at,
            ];
        };

        if ($type === 'summarized') {
            // 🧾 Customer Payments (inflow)
            $customerPayments = collect($customerQuery->get())->map(fn($p) => [
                'type' => 'invoice',
                'date' => Carbon::parse($p->date)->toDateString(), // normalize date
                'bill' => (float) ($p->amount ?? 0),
                'payment' => 0,
                'created_at' => $p->created_at,
            ]);

            // 💵 Supplier Payments (outflow)
            $supplierPayments = collect($supplierQuery->get())->map(fn($p) => [
                'type' => 'payment',
                'date' => Carbon::parse($p->date)->toDateString(), // normalize date
                'bill' => 0,
                'payment' => (float) ($p->amount ?? 0),
                'created_at' => $p->created_at,
            ]);
            $adjustmentRows = $adjustments->map(fn($adjustment) => $formatAdjustment($adjustment, true));

            // 📅 Merge & group by date
            $statement = $customerPayments
                ->merge($supplierPayments)
                ->merge($adjustmentRows)
                ->groupBy('date')
                ->flatMap(function ($rows, $date) {
                    // 🔹 Sort by created_at within each date
                    $rows = $rows->sortBy('created_at');

                    $billSum = (float) $rows->sum('bill');
                    $paymentSum = (float) $rows->sum('payment');

                    $results = [];

                    // ✅ If that date has payments — find earliest created_at among them
                    if ($paymentSum > 0) {
                        $firstPaymentCreatedAt = $rows
                            ->where('type', 'payment')
                            ->min('created_at');

                        $results[] = [
                            'type' => 'payment',
                            'date' => Carbon::parse($date),
                            'bill' => 0,
                            'payment' => $paymentSum,
                            'created_at' => $firstPaymentCreatedAt,
                        ];
                    }

                    // ✅ If that date has invoices — find earliest created_at among them
                    if ($billSum > 0) {
                        $firstInvoiceCreatedAt = $rows
                            ->where('type', 'invoice')
                            ->min('created_at');

                        $results[] = [
                            'type' => 'invoice',
                            'date' => Carbon::parse($date),
                            'bill' => $billSum,
                            'payment' => 0,
                            'created_at' => $firstInvoiceCreatedAt,
                        ];
                    }

                    return collect($results)->sortBy('created_at')->values();
                })
                ->sortBy([
                    ['date', 'asc'],
                    ['created_at', 'asc'],
                ])
                ->values();
        }

        else {
            // 🧾 Customer Payments (detailed)
            $customerPayments = collect($customerQuery->get())->map(fn($p) => [
                'date' => $p->date ?? null,
                'reff_no' => $p->cheque_no ?? $p->slip_no ?? $p->transaction_id ?? $p->reff_no ?? null,
                'type' => 'invoice',
                'method' => $p->method ?? null,
                'bill' => (float) ($p->amount ?? 0),
                'payment' => 0,
                'description' => ($p->customer?->customer_name . ' | ' . $p->customer?->city?->short_title) ?? $p->remarks ?? null,
                'created_at' => $p->created_at ?? null,
            ]);

            // 💵 Supplier Payments (detailed)
            $supplierPayments = collect($supplierQuery->get())->map(fn($p) => [
                'date' => $p->date ?? null,
                'reff_no' => $p->cheque_no ?? $p->slip?->slip_no ?? $p->cheque?->cheque_no ?? $p->transaction_id ?? $p->reff_no ?? null,
                'type' => 'payment',
                'method' => $p->method ?? null,
                'payment' => (float) ($p->amount ?? 0),
                'bill' => 0,
                'description' => $p->supplier?->supplier_name ?? $p->remarks ?? null,
                'created_at' => $p->created_at ?? null,
            ]);
            $adjustmentRows = $adjustments->map(fn($adjustment) => $formatAdjustment($adjustment));

            // 📅 Merge & sort (date → created_at)
            $statement = $customerPayments
                ->merge($supplierPayments)
                ->merge($adjustmentRows)
                ->sortBy([
                    ['date', 'asc'],
                    ['created_at', 'asc'],
                ])
                ->values();
        }

        // 🧮 Totals
        $billTotal = $statement->sum('bill');
        $paymentTotal = $statement->sum('payment');

        $totals = [
            'bill' => $billTotal,
            'payment' => $paymentTotal,
            'balance' => $billTotal - $paymentTotal,
        ];

        // 🧩 Final Response
        return [
            'date' => Carbon::parse($fromDate)->format('d-M-Y') . ' - ' . Carbon::parse($toDate)->format('d-M-Y'),
            'name' => "{$this->account_title} | {$this->bank->short_title}",
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'statements' => $statement,
            'totals' => $totals,
            'category' => 'account',
        ];
    }
}
