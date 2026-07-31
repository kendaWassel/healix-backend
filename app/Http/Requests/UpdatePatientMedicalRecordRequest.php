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
            'chronic_diseases' => 'sometimes|nullable|string',
            'previous_surgeries' => 'sometimes|nullable|string',
            'allergies' => 'sometimes|nullable|string',
            'current_medications' => 'sometimes|nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'chronic_diseases.string' => __('requests.medical_record.chronic_diseases_string'),
            'previous_surgeries.string' => __('requests.medical_record.previous_surgeries_string'),
            'allergies.string' => __('requests.medical_record.allergies_string'),
            'current_medications.string' => __('requests.medical_record.current_medications_string'),
        ];
    }
}
