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
            'drugs.required' => 'A list of drug names is required.',
            'drugs.min' => 'At least two drugs are required to screen for interactions.',
            'drugs.max' => 'A maximum of 20 drugs can be screened per request.',
            'drugs.*.distinct' => 'The medication list contains duplicate drug names.',
        ];
    }
}
