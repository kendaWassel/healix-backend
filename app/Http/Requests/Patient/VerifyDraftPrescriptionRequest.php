<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

class VerifyDraftPrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // `role:patient` middleware already gates the route; this also confirms
        // the authenticated user actually has a linked Patient record.
        return $this->user()?->role === 'patient' && $this->user()->patient !== null;
    }

    public function rules(): array
    {
        return [
            // No patient_id here — the patient is always the authenticated user.
            'medications' => 'required|array|min:1|max:50',
            'medications.*' => 'required|string|max:255|distinct:ignore_case',
        ];
    }

    public function messages(): array
    {
        return [
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
