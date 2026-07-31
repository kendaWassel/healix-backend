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
            'conversation_id.required' => __('requests.speech.conversation_id_required'),
            'conversation_id.exists' => __('requests.speech.conversation_id_exists'),
            'audio.required' => __('requests.speech.audio_required'),
            'audio.file' => __('requests.speech.audio_file'),
            'audio.max' => __('requests.speech.audio_max'),
            'audio.mimes' => __('requests.speech.audio_mimes'),
            'audio.mimetypes' => __('requests.speech.audio_mimetypes'),
        ];
    }
}
