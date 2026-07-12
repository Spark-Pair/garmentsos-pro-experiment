<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class BranchUserAccess extends Model
{
    protected $table = 'branch_user_access';

    protected $fillable = [
        'branch_id',
        'user_id',
        'role',
        'module_key',
        'can_view',
        'can_create',
        'can_update',
        'can_delete',
        'can_switch',
        'can_manage',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_create' => 'boolean',
        'can_update' => 'boolean',
        'can_delete' => 'boolean',
        'can_switch' => 'boolean',
        'can_manage' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
