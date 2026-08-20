<?php

namespace App\Http\Resources;

use App\Support\Locale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * The raw enum values (sender, message_type, status) are unchanged so
     * existing clients keep working; the *_label fields are additive and carry
     * the localized text for display.
     *
     * `transcribed_text` and `detected_symptoms` come from the AI service and
     * are never translated — they are clinical content, not UI copy.
     *
     * is_crisis/severity/diagnosis/specialty/reports: Healix-AI-specific
     * triage fields (see HealixConversationService::sendMessage(), which
     * persists them on the assistant Message row). Always present but
     * false/null on any message from the OTHER assistant
     * (MedicalAssistantService) or a row older than this field's
     * migration — never omitted, so a client can check them
     * unconditionally the same way it already does on a live
     * storeHealixMessage() response.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender' => $this->sender,
            'sender_label' => Locale::label('message_sender', $this->sender),
            'sender_id' => $this->sender_id,
            'message_type' => $this->message_type,
            'message_type_label' => Locale::label('message_type', $this->message_type),
            'message' => $this->message,
            'turn_number' => $this->turn_number,
            'audio_path' => $this->audio_path,
            'transcribed_text' => $this->transcribed_text,
            'detected_symptoms' => $this->detected_symptoms,
            'status' => $this->status,
            'status_label' => Locale::label('message_status', $this->status),
            'is_crisis' => (bool) $this->is_crisis,
            'severity' => $this->severity,
            'diagnosis' => $this->diagnosis,
            'specialty' => $this->specialty,
            'reports' => $this->reports,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
