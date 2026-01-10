<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'consultation_id' => $this->consultation_id,
            'doctor_id' => $this->doctor_id,
            'patient_id' => $this->patient_id,
            'pharmacist_id' => $this->pharmacist_id,
            'diagnosis' => $this->diagnosis,
            'notes' => $this->notes,
            'source' => $this->source,
            'status' => $this->status,
            'prescription_image_id' => $this->prescription_image_id,
            'total_quantity' => $this->total_quantity,
            'total_price' => $this->total_price,
            'consultation' => $this->whenLoaded('consultation'),
            'doctor' => $this->whenLoaded('doctor'),
            'patient' => $this->whenLoaded('patient'),
            'pharmacist' => $this->whenLoaded('pharmacist'),
            'medications' => $this->whenLoaded('medications'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}