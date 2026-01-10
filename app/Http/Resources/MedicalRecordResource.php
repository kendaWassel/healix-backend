<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicalRecordResource extends JsonResource
{
    /**
     * Create a new resource instance.
     *
     * @return void
     */
    
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
                'id' => $this->id,
                'patient_id' => $this->patient_id,
                'doctor_id' => $this->doctor_id,
                'doctor_name' => $this->doctor?->user ? 'Dr. ' . $this->doctor->user->full_name : null,
                'diagnosis' => $this->diagnosis,
                'treatment_plan' => $this->treatment_plan,
                'chronic_diseases' => $this->chronic_diseases,
                'previous_surgeries' => $this->previous_surgeries,
                'allergies' => $this->allergies,
                'current_medications' => $this->current_medications,
                'attachments' => $this->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'file_name' => basename($attachment->file_path),
                        'file_url' => asset('storage/' . ltrim($attachment->file_path, '/')),
                    ];
                }),
                'created_at' => $this->created_at?->toDateTimeString(),
                'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
