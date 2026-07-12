<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserModuleBranchPreference extends Model
{
    protected $fillable = [
        'user_id',
        'module_key',
        'branch_id',
        'selection_mode',
        'branch_ids',
    ];

    protected $casts = [
        'branch_ids' => 'array',
    ];
}
