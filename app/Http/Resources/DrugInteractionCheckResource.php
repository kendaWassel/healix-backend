<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\DrugInteractionCheck
 */
class DrugInteractionCheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'check_type' => $this->check_type,
            'patient_id' => $this->patient_id,
            'highest_severity' => $this->highest_severity,
            'interactions_found' => $this->interactions_found,
            'input' => $this->input,
            'result' => $this->result,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
