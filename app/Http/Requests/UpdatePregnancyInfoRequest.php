<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePregnancyInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'patient' && $this->user()->patient !== null;
    }

    public function rules(): array
    {
        return [
            'is_pregnant' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'is_pregnant.required' => 'Please indicate whether the patient is currently pregnant.',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Pregnancy applies to female patients only.
            $gender = mb_strtolower((string) $this->user()?->patient?->gender);

            if ($this->boolean('is_pregnant') && $gender !== 'female') {
                $validator->errors()->add(
                    'is_pregnant',
                    'Pregnancy information can only be recorded for female patients.'
                );
            }
        });
    }
}
