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
            'message.required' => 'Message content is required.',
            'message.string' => 'Message must be a string.',
            'message.max' => 'Message must not exceed 5000 characters.',
        ];
    }
}
