<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_id',
        'branch_id',
        'tag',
        'quantity',
        'unit',
        'worker_id',
        'fabric_id',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    public function worker()
    {
        return $this->belongsTo(Employee::class, 'worker_id');
    }
}
