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
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => __('requests.lab_analyze.file_required'),
            'file.mimes' => __('requests.lab_analyze.file_mimes'),
            'file.max' => __('requests.lab_analyze.file_max'),
            'gender.in' => __('requests.lab_analyze.gender_in'),
        ];
    }
}
