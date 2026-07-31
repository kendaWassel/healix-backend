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
            'drug_a.required' => __('requests.ddi_interaction.drug_a_required'),
            'drug_b.required' => __('requests.ddi_interaction.drug_b_required'),
            'drug_b.different' => __('requests.ddi_interaction.drug_b_different'),
        ];
    }
}
