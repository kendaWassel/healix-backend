<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consultation_id' => ['nullable', 'integer', 'exists:consultations,id'],
            'patient_id'      => ['required', 'integer', 'exists:patients,id'],
            'doctor_id'       => ['required', 'integer', 'exists:doctors,id'],
            'pharmacist_id'   => ['nullable', 'integer', 'exists:pharmacists,id'],
            'diagnosis'       => ['nullable', 'string', 'max:1000'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'source'          => ['nullable', Rule::in(['consultation', 'upload', 'external'])],

            'medications'                 => ['required', 'array', 'min:1'],
            'medications.*.medication_id' => ['required', 'integer', 'exists:medications,id'],
            'medications.*.boxes'         => ['required', 'integer', 'min:1'],
            'medications.*.price'         => ['nullable', 'numeric', 'min:0'],
            'medications.*.instructions'  => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Patient is required.',
            'doctor_id.required'  => 'Doctor is required.',
            'medications.required'=> 'At least one medication must be provided.',
            'medications.array'   => 'Medications must be an array list.',
        ];
    }
}
