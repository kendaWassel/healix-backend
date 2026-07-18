<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeLabReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    // Formats accepted by the LabInsight parser factory: .csv, .xlsx, .xls, .pdf
    {
        return [
            'file' => 'required|file|mimes:csv,txt,xlsx,xls,pdf|max:10240',
            'age' => 'sometimes|nullable|integer|min:0|max:150',
            'gender' => 'sometimes|nullable|string|in:male,female,other',
            'pre_existing_conditions' => 'sometimes|nullable|array',
            'pre_existing_conditions.*' => 'string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A lab test file is required.',
            'file.mimes' => 'Unsupported file type. Supported formats: CSV, Excel (.xlsx, .xls), PDF.',
            'file.max' => 'The lab test file may not be larger than 10 MB.',
            'gender.in' => 'Gender must be male, female, or other.',
        ];
    }
}
