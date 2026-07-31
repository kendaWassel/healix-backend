<?php

namespace App\Http\Requests\DDI;

use Illuminate\Foundation\Http\FormRequest;

class AllergyCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'drug' => 'required|string|max:255',
            'max_results' => 'sometimes|integer|min:1|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'drug.required' => __('requests.ddi_allergy.drug_required'),
        ];
    }
}
