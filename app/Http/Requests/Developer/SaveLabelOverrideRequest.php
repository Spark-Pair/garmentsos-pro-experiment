<?php

namespace App\Http\Requests\Developer;

use Illuminate\Foundation\Http\FormRequest;

class SaveLabelOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['developer', 'admin'], true);
    }

    public function rules(): array
    {
        return [
            'label_key' => ['required', 'string', 'max:120'],
            'override_text' => ['required', 'string', 'max:80', 'not_regex:/<[^>]*>/'],
        ];
    }
}
