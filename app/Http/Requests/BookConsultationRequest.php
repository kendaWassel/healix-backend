<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ensure the user is a patient
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => 'required|exists:doctors,id',
            'call_type' => 'required|in:call_now,schedule',
            'scheduled_at' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'doctor_id.required' => 'A doctor is required for booking a consultation.',
            'doctor_id.exists' => 'Selected doctor does not exist.',
            'call_type.required' => 'Call type is required.',
            'call_type.in' => 'Call type must be either call_now or schedule.',
            'scheduled_at.date' => 'Scheduled at must be a valid date.',
        ];
    }
}
