<?php

namespace App\Models;

use App\Traits\CustomerComputed;
use App\Traits\Filterable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Customer extends Model
{
    use HasFactory;

    use Filterable, CustomerComputed;

    protected $fillable = [
        'user_id',
        'customer_name',
        'person_name',
        'urdu_title',
        'phone_number',
        'date',
        'category',
        'city_id',
        'address',
    ];

    protected $hidden = [
        'user_id',
        'creator_id',
        'city_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected static function booted()
    {
        // Automatically set creator_id when creating a new Article
        static::creating(function ($thisModel) {
            if (Auth::check()) {
                $thisModel->creator_id = Auth::id();
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }

    protected $appends = ['balance'];

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function city()
    {
        return $this->belongsTo(Setup::class, 'city_id', 'id')->where('type', 'city');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function payments()
    {
        return $this->hasMany(CustomerPayment::class, 'customer_id');
    }

    public function paymentPrograms()
    {
        return $this->hasMany(PaymentProgram::class, 'customer_id');
    }

    public function bankAccounts()
    {
        return $this->morphMany(BankAccount::class, 'sub_category');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    public function statementAdjustments()
    {
        return $this->morphMany(StatementAdjustment::class, 'adjustable');
    }

    public function getBalanceAttribute()
    {
        return $this->calculateBalance();
    }
    public function calculateBalance($fromDate = null, $toDate = null, $formatted = false, $includeGivenDate = true)
    {
        $invoicesQuery = $this->invoices();
        $paymentsQuery = $this->payments()->where('type', '!=', 'DR');
        $adjustmentsQuery = $this->statementAdjustments();

        // Normalize dates to start/end of day
        if ($fromDate) {
            $from = Carbon::parse($fromDate)->startOfDay();
        }
        if ($toDate) {
            $to = Carbon::parse($toDate)->endOfDay();
        }

        // Handle different date scenarios
        if (isset($from, $to)) {
            if ($includeGivenDate) {
                $invoicesQuery->whereBetween('date', [$from, $to]);
                $paymentsQuery->whereBetween('date', [$from, $to]);
                $adjustmentsQuery->whereBetween('date', [$from, $to]);
            } else {
                $invoicesQuery->where('date', '>', $from)->where('date', '<', $to);
                $paymentsQuery->where('date', '>', $from)->where('date', '<', $to);
                $adjustmentsQuery->where('date', '>', $from)->where('date', '<', $to);
            }
        } elseif (isset($from)) {
            $operator = $includeGivenDate ? '>=' : '>';
            $invoicesQuery->where('date', $operator, $from);
            $paymentsQuery->where('date', $operator, $from);
            $adjustmentsQuery->where('date', $operator, $from);
        } elseif (isset($to)) {
            $operator = $includeGivenDate ? '<=' : '<';
            $invoicesQuery->where('date', $operator, $to);
            $paymentsQuery->where('date', $operator, $to);
            $adjustmentsQuery->where('date', $operator, $to);
        }

        // Calculate totals
        $totalInvoices = $invoicesQuery->sum('netAmount') ?? 0;
        $totalPayments = $paymentsQuery->sum('amount') ?? 0;
        $adjustmentsNet = (float) $adjustmentsQuery
            ->get()
            ->sum(fn($adjustment) => (float) $adjustment->net_amount);

        $balance = ($totalInvoices - $totalPayments) + $adjustmentsNet;

        return $formatted ? \App\Support\Money::format($balance) : $balance;
    }
    public function getStatement($fromDate, $toDate, $type = 'general')
    {
        $type = $type ?: 'general';
        // 🧮 Opening & Closing Balances
        $openingBalance = $this->calculateBalance(null, $fromDate, false, false);
        $periodBalance  = $this->calculateBalance($fromDate, $toDate);
        $closingBalance = $openingBalance + $periodBalance;

        // --- Normalize dates ---
        $from = Carbon::parse($fromDate)->startOfDay();
        $to   = Carbon::parse($toDate)->endOfDay();

        // --- Fetch invoices & payments ---
        $invoices = $this->invoices()
            ->whereBetween('date', [$from, $to])
            ->get();

        $payments = $this->payments()
            ->where('type', '!=', 'DR')
            ->whereBetween('date', [$from, $to])
            ->get();
        $adjustments = $this->statementAdjustments()
            ->whereBetween('date', [$from, $to])
            ->get();

        $statement = collect();
        $formatAdjustment = function ($adjustment) {
            $isPlus = $adjustment->direction === 'plus';
            $label = $adjustment->entry_type === 'opening_balance' ? 'Opening Balance' : 'Adjustment';

            return [
                'date' => $adjustment->date,
                'reff_no' => ($adjustment->entry_type === 'opening_balance' ? 'OB' : 'ADJ') . '-' . $adjustment->id,
                'type' => $isPlus ? 'invoice' : 'payment',
                'method' => $label,
                'bill' => $isPlus ? (float) $adjustment->amount : 0,
                'payment' => $isPlus ? 0 : (float) $adjustment->amount,
                'description' => $adjustment->remarks ?: $label,
                'created_at' => $adjustment->created_at,
            ];
        };

        $paymentDescription = function ($p) {
            return $p->cheque_date?->format('d-M-Y, D')
                ?? $p->slip_date?->format('d-M-Y, D')
                ?? (($p->bankAccount?->account_title || $p->bankAccount?->bank?->short_title)
                    ? trim(
                        ($p->bankAccount?->account_title ?? '') .
                        ($p->bankAccount?->bank?->short_title
                            ? ' | ' . $p->bankAccount->bank->short_title
                            : ''),
                        ' |'
                    )
                    : null);
        };

        if ($type === 'summarized') {
            // 🔹 Group invoices by date
            $invoiceGrouped = $invoices->groupBy(fn($i) => Carbon::parse($i->date)->toDateString());
            // 🔹 Group payments by date
            $paymentGrouped = $payments->groupBy(fn($p) => Carbon::parse($p->date)->toDateString());
            $adjustmentGrouped = $adjustments->groupBy(fn($a) => Carbon::parse($a->date)->toDateString());

            // 🔹 Get all unique dates
            $allDates = $invoiceGrouped->keys()->merge($paymentGrouped->keys())->merge($adjustmentGrouped->keys())->unique()->sort();

            foreach ($allDates as $date) {
                $bill = isset($invoiceGrouped[$date]) ? $invoiceGrouped[$date]->sum(fn($i) => (float) $i->netAmount) : 0;
                $payment = isset($paymentGrouped[$date]) ? $paymentGrouped[$date]->sum(fn($p) => (float) $p->amount) : 0;
                $dayAdjustments = $adjustments->filter(fn($adjustment) => Carbon::parse($adjustment->date)->toDateString() === $date);
                $adjustmentBill = $dayAdjustments->where('direction', 'plus')->sum('amount');
                $adjustmentPayment = $dayAdjustments->where('direction', 'minus')->sum('amount');
                $bill += $adjustmentBill;
                $payment += $adjustmentPayment;

                // Add payment first if exists
                if ($payment > 0) {
                    $firstPayment = $paymentGrouped[$date]->sortBy('created_at')->first() ?? $dayAdjustments->where('direction', 'minus')->sortBy('created_at')->first();
                    $statement->push([
                        'type' => 'payment',
                        'date' => Carbon::parse($date),
                        'bill' => 0,
                        'payment' => $payment,
                        'created_at' => $firstPayment->created_at,
                    ]);
                }

                // Add invoice
                if ($bill > 0) {
                    $firstInvoice = $invoiceGrouped[$date]->sortBy('created_at')->first() ?? $dayAdjustments->where('direction', 'plus')->sortBy('created_at')->first();
                    $statement->push([
                        'type' => 'invoice',
                        'date' => Carbon::parse($date),
                        'bill' => $bill,
                        'payment' => 0,
                        'created_at' => $firstInvoice->created_at,
                    ]);
                }
            }

            // Sort by date then created_at
            $statement = $statement->sortBy([
                ['date', 'asc'],
                ['created_at', 'asc'],
            ])->values();
        } elseif ($type === 'general') {
            // 🔹 Detailed invoices + voucher-wise payments
            foreach ($invoices as $i) {
                $statement->push([
                    'date' => $i->date,
                    'reff_no' => $i->invoice_no,
                    'type' => 'invoice',
                    'bill' => (float) $i->netAmount,
                    'payment' => 0,
                    'created_at' => $i->created_at,
                    'source' => [
                        'type' => 'invoice',
                        'id' => $i->id,
                    ],
                ]);
            }

            $paymentGroups = $payments->groupBy(function ($p) {
                return $p->cheque_no ?? $p->slip_no ?? $p->transaction_id ?? $p->reff_no ?? $p->id;
            });

            foreach ($paymentGroups as $voucherNo => $group) {
                $first = $group->sortBy('created_at')->first();
                $statement->push([
                    'date' => $first->date,
                    'reff_no' => $voucherNo,
                    'type' => 'payment',
                    'method' => $first->method,
                    'payment' => (float) $group->sum('amount'),
                    'bill' => 0,
                    'description' => $paymentDescription($first),
                    'created_at' => $first->created_at,
                    'source' => [
                        'type' => 'customer_payment',
                        'id' => $first->id,
                    ],
                ]);
            }

            foreach ($adjustments as $adjustment) {
                $statement->push($formatAdjustment($adjustment));
            }

            $statement = $statement->sortBy([
                ['date', 'asc'],
                ['created_at', 'asc'],
            ])->values();
        } else {
            // 🔹 Detailed mode
            foreach ($invoices as $i) {
                $statement->push([
                    'date' => $i->date,
                    'reff_no' => $i->invoice_no,
                    'type' => 'invoice',
                    'bill' => (float) $i->netAmount,
                    'payment' => 0,
                    'created_at' => $i->created_at,
                    'source' => [
                        'type' => 'invoice',
                        'id' => $i->id,
                    ],
                ]);
            }

            foreach ($payments as $p) {
                $statement->push([
                    'date' => $p->date,
                    'reff_no' => $p->cheque_no ?? $p->slip_no ?? $p->transaction_id ?? $p->reff_no,
                    'type' => 'payment',
                    'method' => $p->method,
                    'payment' => (float) $p->amount,
                    'bill' => 0,
                    'description' => $paymentDescription($p),
                    'created_at' => $p->created_at,
                    'source' => [
                        'type' => 'customer_payment',
                        'id' => $p->id,
                    ],
                ]);
            }

            foreach ($adjustments as $adjustment) {
                $statement->push($formatAdjustment($adjustment));
            }

            // Sort by date then created_at
            $statement = $statement->sortBy([
                ['date', 'asc'],
                ['created_at', 'asc'],
            ])->values();
        }

        // 📊 Totals
        $totals = [
            'bill' => $statement->sum('bill'),
            'payment' => $statement->sum('payment'),
            'balance' => $statement->sum('bill') - $statement->sum('payment'),
        ];

        return [
            'date' => $from->format('d-M-Y') . ' - ' . $to->format('d-M-Y'),
            'name' => "{$this->customer_name} | {$this->city->title}",
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'statements' => $statement,
            'totals' => $totals,
            'category' => 'customer',
        ];
    }
}
