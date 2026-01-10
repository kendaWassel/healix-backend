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
            'diagnosis.string' => 'Diagnosis must be a string.',
            'treatment_plan.string' => 'Treatment plan must be a string.',
            'current_medications.string' => 'Current medications must be a string.',
            'chronic_diseases.string' => 'Chronic diseases must be a string.',
            'previous_surgeries.string' => 'Previous surgeries must be a string.',
            'allergies.string' => 'Allergies must be a string.',
            'attachments_id.array' => 'Attachments must be an array.',
            'attachments_id.*.integer' => 'Each attachment ID must be an integer.',
            'attachments_id.*.exists' => 'Each attachment must exist.',
        ];
    }
}