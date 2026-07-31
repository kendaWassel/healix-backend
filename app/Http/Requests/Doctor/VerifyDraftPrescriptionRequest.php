<?php

namespace App\Http\Requests\Doctor;

use Illuminate\Foundation\Http\FormRequest;

class VerifyDraftPrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is already gated by role:doctor middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => 'required|integer|exists:patients,id',
            // The doctor's draft medication list — the single source of truth
            // for this decision-support check. Nothing is persisted.
            'medications' => 'required|array|min:1|max:50',
            'medications.*' => 'required|string|max:255|distinct:ignore_case',
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => __('requests.verify_draft_prescription.patient_id_required'),
            'patient_id.exists' => __('requests.verify_draft_prescription.patient_id_exists'),
            'medications.required' => __('requests.verify_draft_prescription.medications_required'),
            'medications.min' => __('requests.verify_draft_prescription.medications_min'),
            'medications.max' => __('requests.verify_draft_prescription.medications_max'),
            'medications.*.required' => __('requests.verify_draft_prescription.medication_required'),
            'medications.*.distinct' => __('requests.verify_draft_prescription.medication_distinct'),
        ];
    }

    /**
     * Trim medication names before validation so " Warfarin " and "Warfarin"
     * are treated consistently (and empty strings are caught by `required`).
     */
    protected function prepareForValidation(): void
    {
        $medications = $this->input('medications');

        if (is_array($medications)) {
            $this->merge([
                'medications' => array_map(
                    fn ($m) => is_string($m) ? trim($m) : $m,
                    $medications
                ),
            ]);
        }
    }
}
