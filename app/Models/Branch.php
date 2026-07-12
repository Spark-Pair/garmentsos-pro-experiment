<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'code',
        'prefix',
        'status',
        'logo_path',
        'display_name',
        'owner_name',
        'header_text',
        'footer_text',
        'terms_text',
        'phone',
        'email',
        'address',
        'city',
        'province',
        'ntn_cnic',
        'strn_sntn',
        'is_main',
        'metadata',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'metadata' => 'array',
    ];

    public function moduleSettings(): HasMany
    {
        return $this->hasMany(BranchModuleSetting::class);
    }

    public function accessRows(): HasMany
    {
        return $this->hasMany(BranchUserAccess::class);
    }

    public function displayName(): string
    {
        return $this->display_name ?: $this->name;
    }
}
