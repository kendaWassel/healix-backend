<?php

namespace App\Http\Requests\DDI;

use Illuminate\Foundation\Http\FormRequest;

class ConditionCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medications' => 'required|array|min:1|max:100',
            'medications.*' => 'required|string|max:255',
            'conditions' => 'required|array|min:1|max:100',
            'conditions.*' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'medications.required' => __('requests.ddi_condition.medications_required'),
            'medications.array' => __('requests.ddi_condition.medications_array'),
            'medications.min' => __('requests.ddi_condition.medications_min'),
            'conditions.required' => __('requests.ddi_condition.conditions_required'),
            'conditions.array' => __('requests.ddi_condition.conditions_array'),
            'conditions.min' => __('requests.ddi_condition.conditions_min'),
        ];
    }
}
