<?php

namespace App\Http\Requests\Developer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveModuleSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'developer';
    }

    public function rules(): array
    {
        return [
            'module_key' => ['required', 'string', Rule::in(array_keys(config('modules', [])))],
            'enabled' => ['required', 'boolean'],
            'visible_in_sidebar' => ['nullable', 'boolean'],
        ];
    }
}
