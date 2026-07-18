<?php

namespace App\Http\Requests\Pharmacist;

use App\Models\Prescription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Medications are entered manually ONLY for uploaded image prescriptions.
            // Electronic (doctor_written) prescriptions already have them in the DB.
            'medications' => [
                Rule::requiredIf(fn () => $this->isUploadedPrescription()),
                'array',
                'min:1',
                'max:50',
            ],
            'medications.*' => 'required|string|max:255|distinct:ignore_case',
        ];
    }

    public function messages(): array
    {
        return [
            'medications.required' => 'Please enter the medications listed on the prescription image.',
            'medications.min' => 'At least one medication is required.',
            'medications.max' => 'A maximum of 50 medications can be entered at once.',
            'medications.*.required' => 'Medication name cannot be empty.',
            'medications.*.distinct' => 'The medication list contains duplicates.',
        ];
    }

    /**
     * Whether the prescription being verified is an uploaded image prescription
     * (pharmacist must type the medications) rather than an electronic one.
     */
    public function isUploadedPrescription(): bool
    {
        return Prescription::whereKey($this->route('prescription_id'))->value('source')
            === 'patient_uploaded';
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
