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
            'title.required' => __('requests.conversation.title_required'),
            'title.string' => __('requests.conversation.title_string'),
            'title.max' => __('requests.conversation.title_max'),
        ];
    }
}
