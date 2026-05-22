<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

class StatementAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'entry_type',
        'direction',
        'amount',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date',
    ];

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
}
