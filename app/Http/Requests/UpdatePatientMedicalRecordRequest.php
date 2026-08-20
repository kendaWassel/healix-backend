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
            'pre_existing_conditions' => 'sometimes|nullable|array',
            'pre_existing_conditions.*' => 'string|max:255',
            'other_conditions' => 'sometimes|nullable|string|max:1000',
            'previous_surgeries' => 'sometimes|nullable|string',
            'allergies' => 'sometimes|nullable|array',
            'allergies.*' => 'string|max:255',
            'current_medications' => 'sometimes|nullable|array',
            'current_medications.*' => 'string|max:255',
            'is_pregnant' => 'sometimes|nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'chronic_diseases.array' => __('requests.medical_record.chronic_diseases_array'),
            'chronic_diseases.*.string' => __('requests.medical_record.chronic_diseases_string'),
            'pre_existing_conditions.array' => __('requests.medical_record.pre_existing_conditions_array'),
            'pre_existing_conditions.*.string' => __('requests.medical_record.pre_existing_conditions_string'),
            'other_conditions.string' => __('requests.medical_record.other_conditions_string'),
            'previous_surgeries.string' => __('requests.medical_record.previous_surgeries_string'),
            'allergies.array' => __('requests.medical_record.allergies_array'),
            'allergies.*.string' => __('requests.medical_record.allergies_string'),
            'current_medications.array' => __('requests.medical_record.current_medications_array'),
            'current_medications.*.string' => __('requests.medical_record.current_medications_string'),
            'is_pregnant.boolean' => __('requests.medical_record.is_pregnant_boolean'),     
        ];
    }
}
