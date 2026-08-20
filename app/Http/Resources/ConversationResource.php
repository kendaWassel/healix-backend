<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'title' => $this->title,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            // Lets the app show a "book now" button on a reopened
            // conversation without re-deriving it from message history —
            // Assessment is 1:1 with Conversation (see
            // HealixConversationService::persistDiagnosisOutcome's
            // updateOrCreate(['conversation_id' => ...])). Null on any
            // conversation that never reached a real differential.
            'assessment_id' => $this->assessment?->id,
            'messages_count' => $this->whenCounted('messages'),
            'latest_message' => new MessageResource($this->whenLoaded('latestMessage')),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
