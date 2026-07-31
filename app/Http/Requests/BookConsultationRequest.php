<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
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
            'doctor_id.required' => __('requests.book_consultation.doctor_id_required'),
            'doctor_id.exists' => __('requests.book_consultation.doctor_id_exists'),
            'call_type.required' => __('requests.book_consultation.call_type_required'),
            'call_type.in' => __('requests.book_consultation.call_type_in'),
            'scheduled_at.date' => __('requests.book_consultation.scheduled_at_date'),
        ];
    }
}