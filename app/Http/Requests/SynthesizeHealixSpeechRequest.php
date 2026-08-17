<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SynthesizeHealixSpeechRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Same max as StoreMessageRequest's own 'message' field —
            // this is typically that same reply text read back aloud.
            'text' => 'required|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'text.required' => __('requests.synthesize.text_required'),
            'text.string' => __('requests.synthesize.text_string'),
            'text.max' => __('requests.synthesize.text_max'),
        ];
    }
}
