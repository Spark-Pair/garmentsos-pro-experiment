<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\SetupComputed;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Setup extends Model
{
    use HasFactory;

    use Filterable, SetupComputed;

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'type',
        'title',
        'short_title',
        'branch_id',
    ];

    public function scopeWorkerTypesNotE($query)
    {
        return $query->where('type', 'worker_type')
                    ->where('short_title', 'not like', '%|E%');
    }
}
