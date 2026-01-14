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
            'diagnosis'       => ['nullable', 'string', 'max:1000'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'source'          => ['nullable', Rule::in(['consultation', 'upload', 'external'])],

            'medicines'                 => ['required', 'array', 'min:1'],
            'medicines.*.name'          => ['required', 'string', 'max:255'],
            'medicines.*.boxes'         => ['required', 'integer', 'min:1'],
            'medicines.*.dosage'         => ['nullable', 'string', 'max:500'],
            'medicines.*.instructions'  => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'medicines.required'=> 'At least one medication must be provided.',
            'medicines.array'   => 'Medications must be an array list.',
        ];
    }
}
