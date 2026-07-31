<?php

namespace App\Http\Requests\Pharmacist;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership/role is enforced by PrescriptionPolicy::verify in the controller.
        return true;
    }

    public function rules(): array
    {
        return [
            // The pharmacist confirms/enters the dispensed medications for
            // EVERY prescription (uploaded image or electronic alike) — this
            // is the single source of truth verification runs against.
            'medications' => 'required|array|min:1|max:50',
            'medications.*' => 'required|string|max:255|distinct:ignore_case',
        ];
    }

    public function messages(): array
    {
        return [
            'medications.required' => __('requests.verify_prescription.medications_required'),
            'medications.min' => __('requests.verify_prescription.medications_min'),
            'medications.max' => __('requests.verify_prescription.medications_max'),
            'medications.*.required' => __('requests.verify_prescription.medication_required'),
            'medications.*.distinct' => __('requests.verify_prescription.medication_distinct'),
        ];
    }

    /**
     * Trim medication names before validation so " Panadol " and "Panadol"
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
