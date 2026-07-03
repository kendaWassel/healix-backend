<?php

namespace App\Http\Requests\DDI;

use Illuminate\Foundation\Http\FormRequest;

class CheckInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'drug_a' => 'required|string|max:255',
            'drug_b' => 'required|string|max:255|different:drug_a',
            'include_alternatives' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'drug_a.required' => 'The first drug name is required.',
            'drug_b.required' => 'The second drug name is required.',
            'drug_b.different' => 'Please provide two different drug names.',
        ];
    }
}
