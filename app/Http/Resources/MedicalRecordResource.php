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
        $images = [];
        $files = [];

        $this->uploads->each(function ($upload) use (&$images, &$files) {
            $uploadData = [
                'id' => $upload->id,
                'file_name' => basename($upload->file_path),
                    'file_url' => str_starts_with($upload->mime, 'image/')
                        ? asset('storage/' . ltrim($upload->file_path, '/'))
                        : ($request->getSchemeAndHttpHost() . route('medical-record.attachment.download', ['id' => $upload->id], false)),
            ];

            // Check if it's an image based on MIME type
            if (str_starts_with($upload->mime, 'image/')) {
                $images[] = $uploadData;
            } else {
                $files[] = $uploadData;
            }
        });

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
                'images' => $images,
                'files' => $files,
                'created_at' => $this->created_at?->toDateTimeString(),
                'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
