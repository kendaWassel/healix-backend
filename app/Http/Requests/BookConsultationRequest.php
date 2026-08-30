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
            'scheduled_at' => 'nullable',
            // Optional: only present when booking originates from the AI
            // assessment result screen ("book with recommended specialty").
            // The plain direct-booking flow (no AI involved) omits both.
            'conversation_id' => 'nullable|integer|exists:conversations,id',
            'assessment_id' => 'nullable|integer|exists:assessments,id',
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
            'conversation_id.exists' => __('requests.book_consultation.conversation_id_exists'),
            'assessment_id.exists' => __('requests.book_consultation.assessment_id_exists'),
        ];
    }
}