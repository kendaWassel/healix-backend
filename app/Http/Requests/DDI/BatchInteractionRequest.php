<?php

namespace App\Http\Requests\DDI;

use Illuminate\Foundation\Http\FormRequest;

class BatchInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pairs' => 'required|array|min:1|max:50',
            'pairs.*.drug_a' => 'required|string|max:255',
            'pairs.*.drug_b' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'pairs.required' => 'At least one drug pair is required.',
            'pairs.max' => 'A maximum of 50 drug pairs can be checked per request.',
            'pairs.*.drug_a.required' => 'Each pair must include the first drug name.',
            'pairs.*.drug_b.required' => 'Each pair must include the second drug name.',
        ];
    }
}
