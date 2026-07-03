<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Conversation title is required.',
            'title.string' => 'Conversation title must be a string.',
            'title.max' => 'Conversation title must not exceed 255 characters.',
        ];
    }
}
