<?php

namespace App\Http\Requests\Developer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBrandingSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'developer';
    }

    public function rules(): array
    {
        $key = (string) $this->input('key');
        $valueRules = ['nullable', 'string', 'max:120', 'not_regex:/[<>]/'];

        if (str_contains($key, 'color')) {
            $valueRules[] = 'regex:/^#[0-9a-fA-F]{6}$/';
        }

        return [
            'key' => ['required', 'string', 'max:120', Rule::in(array_keys(config('branding', [])))],
            'value' => $valueRules,
        ];
    }
}
