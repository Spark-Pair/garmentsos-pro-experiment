<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleSetting extends Model
{
    protected $fillable = [
        'module_key',
        'enabled',
        'visible_in_sidebar',
        'reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'visible_in_sidebar' => 'boolean',
    ];
}
