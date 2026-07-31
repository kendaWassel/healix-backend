<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        $doctor = $user->doctor ?? null;
        $careProvider = $user->careProvider ?? null;
        return $doctor || ($careProvider && in_array($careProvider->type, ['nurse', 'physiotherapist']));
    }

    public function rules(): array
    {
        return [
            'diagnosis' => 'nullable|string',
            'treatment_plan' => 'nullable|string',
            'current_medications' => 'nullable|string',
            'chronic_diseases' => 'nullable|string',
            'previous_surgeries' => 'nullable|string',
            'allergies' => 'nullable|string',
            'attachments_id' => 'nullable|array',
            'attachments_id.*' => 'integer|exists:uploads,id',
        ];
    }

    public function messages(): array
    {
        return [
            'diagnosis.string' => __('requests.medical_record.diagnosis_string'),
            'treatment_plan.string' => __('requests.medical_record.treatment_plan_string'),
            'current_medications.string' => __('requests.medical_record.current_medications_string'),
            'chronic_diseases.string' => __('requests.medical_record.chronic_diseases_string'),
            'previous_surgeries.string' => __('requests.medical_record.previous_surgeries_string'),
            'allergies.string' => __('requests.medical_record.allergies_string'),
            'attachments_id.array' => __('requests.medical_record.attachments_array'),
            'attachments_id.*.integer' => __('requests.medical_record.attachments_integer'),
            'attachments_id.*.exists' => __('requests.medical_record.attachments_exists'),
        ];
    }
}