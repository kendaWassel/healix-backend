<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TranscribeSpeechRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conversation_id' => 'required|integer|exists:conversations,id',
            'audio' => [
                'required',
                'file',
                'max:10240',
                'mimes:m4a,mp3,wav,ogg,webm',
                'mimetypes:audio/mp4,audio/x-m4a,audio/mpeg,audio/wav,audio/x-wav,audio/ogg,audio/webm,video/webm',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'conversation_id.required' => 'Conversation ID is required.',
            'conversation_id.exists' => 'The selected conversation does not exist.',
            'audio.required' => 'Audio file is required.',
            'audio.file' => 'The uploaded item must be a valid audio file.',
            'audio.max' => 'Audio file size must not exceed 10MB.',
            'audio.mimes' => 'Audio must be one of: m4a, mp3, wav, ogg, webm.',
            'audio.mimetypes' => 'Audio must be a supported audio format.',
        ];
    }
}
