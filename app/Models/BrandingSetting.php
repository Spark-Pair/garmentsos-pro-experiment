<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandingSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'created_by',
        'updated_by',
    ];
}
