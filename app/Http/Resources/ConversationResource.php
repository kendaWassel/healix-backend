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
            'messages_count' => $this->whenCounted('messages'),
            'latest_message' => new MessageResource($this->whenLoaded('latestMessage')),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
