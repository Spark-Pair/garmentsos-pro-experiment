<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

class StatementAdjustment extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'date',
        'entry_type',
        'direction',
        'amount',
        'remarks',
        'branch_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected $appends = ['net_amount'];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->creator_id = Auth::id();
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function adjustable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getNetAmountAttribute(): float
    {
        $amount = (float) ($this->amount ?? 0);

        return $this->direction === 'minus' ? -$amount : $amount;
    }

    public function toFormattedArray(): array
    {
        $adjustable = $this->adjustable;
        $category = $this->categoryLabel();

        return [
            'id' => $this->id,
            'date' => $this->date?->format('d-M-Y, D'),
            'date_raw' => $this->date?->format('Y-m-d'),
            'category' => $category,
            'name' => $this->adjustableName(),
            'entry_type' => str_replace('_', ' ', $this->entry_type),
            'entry_type_raw' => $this->entry_type,
            'direction' => $this->direction === 'plus' ? 'Debit' : 'Credit',
            'direction_raw' => $this->direction,
            'amount' => \App\Support\Money::format($this->amount),
            'amount_raw' => (float) $this->amount,
            'remarks' => $this->remarks ?: '-',
            'adjustable_id' => $adjustable?->id,
            'adjustable_type' => $this->adjustable_type,
            'oncontextmenu' => 'generateContextMenu(event)',
            'onclick' => 'generateModal(this)',
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'id':
                return $query->where('id', $value);
            case 'category':
                $class = match ($value) {
                    'customer' => Customer::class,
                    'supplier' => Supplier::class,
                    'bank_account' => BankAccount::class,
                    default => null,
                };

                return $class ? $query->where('adjustable_type', $class) : $query;
            case 'name':
                return $query->whereHasMorph('adjustable', [Customer::class, Supplier::class, BankAccount::class], function ($q, $type) use ($value) {
                    match ($type) {
                        Customer::class => $q->where('customer_name', 'like', "%$value%"),
                        Supplier::class => $q->where('supplier_name', 'like', "%$value%"),
                        BankAccount::class => $q->where('account_title', 'like', "%$value%"),
                        default => $q,
                    };
                });
            case 'entry_type':
                return $query->where('entry_type', $value);
            case 'direction':
                return $query->where('direction', $value);
            case 'amount':
                return $query->where('amount', str_replace(',', '', $value));
            case 'date':
                $start = $value['start'] ?? null;
                $end = $value['end'] ?? null;
                if ($start && $end) {
                    \App\Support\DateRange::apply($query, 'date', $start, $end);
                }
                return $query;
            default:
                return $query->where($key, 'like', "%$value%");
        }
    }

    private function categoryLabel(): string
    {
        return match ($this->adjustable_type) {
            Customer::class => 'Customer',
            Supplier::class => 'Supplier',
            BankAccount::class => 'Bank Account',
            default => 'Unknown',
        };
    }

    private function adjustableName(): string
    {
        $adjustable = $this->adjustable;

        if ($adjustable instanceof Customer) {
            return trim($adjustable->customer_name . ' | ' . ($adjustable->city?->short_title ?? $adjustable->city?->title ?? ''), ' |');
        }

        if ($adjustable instanceof Supplier) {
            return $adjustable->supplier_name;
        }

        if ($adjustable instanceof BankAccount) {
            return trim($adjustable->account_title . ' | ' . ($adjustable->bank?->short_title ?? ''), ' |');
        }

        return '-';
    }
}
