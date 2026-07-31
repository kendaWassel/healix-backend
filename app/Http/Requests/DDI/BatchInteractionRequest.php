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
            'pairs.required' => __('requests.ddi_batch.pairs_required'),
            'pairs.max' => __('requests.ddi_batch.pairs_max'),
            'pairs.*.drug_a.required' => __('requests.ddi_batch.drug_a_required'),
            'pairs.*.drug_b.required' => __('requests.ddi_batch.drug_b_required'),
        ];
    }
}
