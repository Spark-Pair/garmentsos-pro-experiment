<?php

namespace App\Http\Requests\Developer;

use Illuminate\Foundation\Http\FormRequest;

class SaveBrandingSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['developer', 'admin'], true);
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:120'],
            'value' => ['nullable', 'string', 'max:120', 'not_regex:/<[^>]*>/'],
        ];
    }
}
