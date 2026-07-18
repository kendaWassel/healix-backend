<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender' => $this->sender,
            'sender_id' => $this->sender_id,
            'message_type' => $this->message_type,
            'message' => $this->message,
            'turn_number' => $this->turn_number,
            'audio_path' => $this->audio_path,
            'transcribed_text' => $this->transcribed_text,
            'detected_symptoms' => $this->detected_symptoms,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
