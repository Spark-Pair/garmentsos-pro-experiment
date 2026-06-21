<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportOfflineLicenseRequest extends FormRequest
{
    protected $dontFlash = ['signed_license'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signed_license' => ['required', 'string'],
        ];
    }
}
