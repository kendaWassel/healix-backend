<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskHealthQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => 'required|string|max:2000',
            'locale' => 'sometimes|string|in:ar,en',
        ];
    }

    public function messages(): array
    {
        return [
            'question.required' => __('requests.health_question.question_required'),
            'question.string' => __('requests.health_question.question_string'),
            'question.max' => __('requests.health_question.question_max'),
        ];
    }
}
