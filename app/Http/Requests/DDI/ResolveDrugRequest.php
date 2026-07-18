<?php

namespace App\Http\Requests\DDI;

use Illuminate\Foundation\Http\FormRequest;

class ResolveDrugRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'A drug name is required.',
        ];
    }
}
