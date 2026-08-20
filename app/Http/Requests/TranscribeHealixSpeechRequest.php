<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TranscribeHealixSpeechRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Same constraints as TranscribeSpeechRequest's own 'audio'
            // rule (the other AI backend's speech upload) — no
            // 'conversation_id' field here, unlike that one: this
            // request's conversation comes from the route's own
            // {conversation} route-model binding instead.
            'audio' => [
                'required',
                'file',
                'max:10240',
                'mimes:m4a,mp3,wav,ogg,webm,mp4',
                'mimetypes:audio/mp4,audio/x-m4a,audio/mpeg,audio/wav,audio/x-wav,audio/ogg,audio/webm,video/webm,video/mp4',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'audio.required' => __('requests.speech.audio_required'),
            'audio.file' => __('requests.speech.audio_file'),
            'audio.max' => __('requests.speech.audio_max'),
            'audio.mimes' => __('requests.speech.audio_mimes'),
            'audio.mimetypes' => __('requests.speech.audio_mimetypes'),
        ];
    }
}
