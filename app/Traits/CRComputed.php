<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use App\Models\BankAccount;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait CRComputed
{
    public function toFormattedArray()
    {
        $newPayments = collect($this->new_payments ?? []);
        $returnPayments = collect($this->return_payments ?? []);
        $getVal = function ($item, $key) {
            if (is_array($item)) return $item[$key] ?? null;
            if (is_object($item)) return $item->$key ?? null;
            return null;
        };

        $allIds = $returnPayments->map(fn($p) => $getVal($p, 'payment_id') ?? $getVal($p, 'id'))
            ->merge($newPayments->map(fn($p) => $getVal($p, 'payment_id') ?? $getVal($p, 'id')))
            ->filter()
            ->unique()
            ->values();

        $paymentsMap = $allIds->isEmpty()
            ? collect()
            : SupplierPayment::with(['bankAccount.bank', 'bankAccount.subCategory'])
                ->whereIn('id', $allIds)
                ->get()
                ->keyBy('id');

        $bankAccountIds = $newPayments->map(fn($p) => $getVal($p, 'bank_account_id'))
            ->merge($returnPayments->map(fn($p) => $getVal($p, 'bank_account_id')))
            ->filter()
            ->unique()
            ->values();

        $bankAccounts = $bankAccountIds->isEmpty()
            ? collect()
            : BankAccount::with('bank', 'subCategory')
                ->whereIn('id', $bankAccountIds)
                ->get()
                ->keyBy('id');

        $mapPayment = function ($item, string $type) use ($paymentsMap, $bankAccounts, $getVal) {
            $id = $getVal($item, 'payment_id') ?? $getVal($item, 'id');
            $sp = $id ? $paymentsMap->get($id) : null;
            $bankAccId = $getVal($item, 'bank_account_id');
            $bankAcc = $sp?->bankAccount ?: ($bankAccId ? $bankAccounts->get($bankAccId) : null);

            $beneficiary = $bankAcc?->subCategory?->supplier_name
                ?? $bankAcc?->subCategory?->customer_name
                ?? 'Self Account';

            return [
                'type' => $type,
                'date' => $sp?->date?->format('d-M-Y, D') ?? $getVal($item, 'date'),
                'method' => $sp?->method ?? ($getVal($item, 'method') ?? '-'),
                'amount' => $sp?->amount ?? ($getVal($item, 'amount') ?? 0),
                'reff' => $sp?->new_reff_no ?? $getVal($item, 'reff_no') ?? $getVal($item, 'data_value') ?? '-',
                'beneficiary' => $beneficiary ?? '-',
                'account_title' => $bankAcc?->account_title ?? '-',
                'bank' => $bankAcc?->bank?->short_title ?? '-',
            ];
        };

        return [
            'id' => $this->id,
            'date' => $this->date->format('d-M-Y, D'),
            'amount' => collect($this->new_payments)->sum('amount'),
            'c_r_no' => $this->c_r_no,
            'voucher_no' => $this->voucher?->voucher_no ?? '-',
            'supplier_name' => $this->voucher?->supplier?->supplier_name ?? app('client_company')->name,
            'new_payments_count' => $newPayments->count(),
            'return_payments_count' => $returnPayments->count(),
            'new_payments_amount' => $newPayments->sum('amount'),
            'return_payments_amount' => $returnPayments->sum('amount'),
            'new_payments_details' => $newPayments->map(fn($p) => $mapPayment($p, 'New'))->values(),
            'return_payments_details' => $returnPayments->map(fn($p) => $mapPayment($p, 'Return'))->values(),
            'data' => $this,
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'supplier_name':
                return $query->where(function ($query) use ($value) {

                    // Case 1: supplier exists → supplier_name
                    $query->whereHas('voucher.supplier', function ($q) use ($value) {
                        $q->where('supplier_name', 'like', "%{$value}%");
                    })

                    // Case 2: supplier does NOT exist → fallback to client_company name
                    ->orWhere(function ($q) use ($value) {
                        $q->whereDoesntHave('voucher.supplier')
                        ->where(app('client_company')->name, 'like', "%{$value}%");
                    });

                });

            case 'voucher_no':
                return $query->whereHas('voucher', function ($q) use ($value) {
                    $q->where('voucher_no', 'like', "%{$value}%");
                });

            case 'date':
                $start = $value['start'] ?? null;
                $end   = $value['end'] ?? null;

                if (!$start || !$end) return $query->where('method', 'cash');


                return $query->where(function ($q) use ($start, $end) {
                    // 1️⃣ slip_date exists
                    $q->Where(function ($q) use ($start, $end) {
                        $q->whereBetween('date', [$start.' 00:00:00', $end.' 23:59:59']);
                    });
                });

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
