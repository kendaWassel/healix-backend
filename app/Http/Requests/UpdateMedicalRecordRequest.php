<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $doctor = $user->doctor ?? null;
        $careProvider = $user->careProvider ?? null;
        return $doctor || ($careProvider && in_array($careProvider->type, ['nurse', 'physiotherapist']));
    }

    public function rules(): array
    {
        return [
            'diagnosis' => 'nullable|string',
            'treatment_plan' => 'nullable|string',
            'current_medications' => 'nullable|array',
            'current_medications.*' => 'string|max:255',
            // JSON array of DrugCentral-standard condition names (picker).
            'chronic_diseases' => 'sometimes|nullable|array',
            'chronic_diseases.*' => 'string|max:255',
            // JSON array of values from the PRE_EXISTING_CONDITIONS picker — feeds lab analysis matching only.
            'pre_existing_conditions' => 'sometimes|nullable|array',
            'pre_existing_conditions.*' => 'string|max:255',
            'other_conditions' => 'sometimes|nullable|string|max:1000',
            'previous_surgeries' => 'nullable|string',
            'allergies' => 'nullable|array',
            'allergies.*' => 'string|max:255',
            'attachments_id' => 'nullable|array',
            'attachments_id.*' => 'integer|exists:uploads,id',
        ];
    }

    public function messages(): array
    {
        return [
            'diagnosis.string' => __('requests.medical_record.diagnosis_string'),
            'treatment_plan.string' => __('requests.medical_record.treatment_plan_string'),
            'current_medications.array' => __('requests.medical_record.current_medications_array'),
            'current_medications.*.string' => __('requests.medical_record.current_medications_string'),
            'chronic_diseases.string' => __('requests.medical_record.chronic_diseases_string'),
            'pre_existing_conditions.array' => __('requests.medical_record.pre_existing_conditions_array'),
            'pre_existing_conditions.*.string' => __('requests.medical_record.pre_existing_conditions_string'),
            'previous_surgeries.string' => __('requests.medical_record.previous_surgeries_string'),
            'allergies.array' => __('requests.medical_record.allergies_array'),
            'allergies.*.string' => __('requests.medical_record.allergies_string'),
            'attachments_id.array' => __('requests.medical_record.attachments_array'),
            'attachments_id.*.integer' => __('requests.medical_record.attachments_integer'),
            'attachments_id.*.exists' => __('requests.medical_record.attachments_exists'),
        ];
    }
}