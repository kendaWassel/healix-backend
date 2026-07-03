<?php

namespace App\Http\Requests\DDI;

use Illuminate\Foundation\Http\FormRequest;

class PregnancyCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'drug_a' => 'required|string|max:255',
            'drug_b' => 'sometimes|nullable|string|max:255',
            'live_api' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'drug_a.required' => 'A drug name is required.',
        ];
    }
}
