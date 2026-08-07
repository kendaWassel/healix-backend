<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'patient' && $this->user()->patient !== null;
    }

    /**
     * Patients may only edit their own self-reported history. Diagnosis and
     * treatment plan remain provider-only (see UpdateMedicalRecordRequest).
     */
    public function rules(): array
    {
        return [
            'chronic_diseases' => 'sometimes|nullable|array',
            'chronic_diseases.*' => 'string|max:255',
            'previous_surgeries' => 'sometimes|nullable|string',
            'allergies' => 'sometimes|nullable|array',
            'allergies.*' => 'string|max:255',
            'current_medications' => 'sometimes|nullable|array',
            'current_medications.*' => 'string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'chronic_diseases.array' => __('requests.medical_record.chronic_diseases_array'),
            'chronic_diseases.*.string' => __('requests.medical_record.chronic_diseases_string'),
            'previous_surgeries.string' => __('requests.medical_record.previous_surgeries_string'),
            'allergies.array' => __('requests.medical_record.allergies_array'),
            'allergies.*.string' => __('requests.medical_record.allergies_string'),
            'current_medications.array' => __('requests.medical_record.current_medications_array'),
            'current_medications.*.string' => __('requests.medical_record.current_medications_string'),
        ];
    }
}
