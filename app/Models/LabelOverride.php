<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabelOverride extends Model
{
    protected $fillable = [
        'label_key',
        'locale',
        'default_text',
        'override_text',
        'created_by',
        'updated_by',
    ];
}
