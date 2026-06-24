<?php

namespace App\Http\Requests\Developer;

use Illuminate\Foundation\Http\FormRequest;

class SaveModuleSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['developer', 'admin'], true);
    }

    public function rules(): array
    {
        return [
            'module_key' => ['required', 'string', 'in:articles,customers,suppliers'],
            'enabled' => ['required', 'boolean'],
            'visible_in_sidebar' => ['nullable', 'boolean'],
        ];
    }
}
