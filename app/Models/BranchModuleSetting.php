<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchModuleSetting extends Model
{
    protected $fillable = [
        'branch_id',
        'module_key',
        'branch_enabled',
        'default_branch_id',
        'allow_user_switching',
        'status',
        'metadata',
    ];

    protected $casts = [
        'branch_enabled' => 'boolean',
        'allow_user_switching' => 'boolean',
        'metadata' => 'array',
    ];

    public function defaultBranch()
    {
        return $this->belongsTo(Branch::class, 'default_branch_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
