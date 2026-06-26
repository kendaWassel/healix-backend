<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'task_id' => $this->task_id,
            'delivery_id' => $this->delivery_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'is_online' => $this->resource->isOnline(),
        ];
    }
}
