<?php

namespace App\Http\Requests\DDI;

use Illuminate\Foundation\Http\FormRequest;

class ScreenMedicationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 20 drugs = 190 pairwise checks on the DDI service; keep it bounded.
            'drugs' => 'required|array|min:2|max:20',
            'drugs.*' => 'required|string|max:255|distinct:ignore_case',
        ];
    }

    public function messages(): array
    {
        return [
            'drugs.required' => __('requests.ddi_screen.drugs_required'),
            'drugs.min' => __('requests.ddi_screen.drugs_min'),
            'drugs.max' => __('requests.ddi_screen.drugs_max'),
            'drugs.*.distinct' => __('requests.ddi_screen.drugs_distinct'),
        ];
    }
}
