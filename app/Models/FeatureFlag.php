<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $fillable = [
        'flag_key',
        'enabled',
        'value',
        'type',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
