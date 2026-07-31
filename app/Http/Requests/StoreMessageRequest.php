<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => 'required|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => __('requests.message.message_required'),
            'message.string' => __('requests.message.message_string'),
            'message.max' => __('requests.message.message_max'),
        ];
    }
}
