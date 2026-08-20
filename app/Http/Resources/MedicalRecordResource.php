<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicalRecordResource extends JsonResource
{
  
    
    public function toArray(Request $request): array
    {
     
        $images = [];
        $files = [];

        $this->uploads?->each(function ($upload) use (&$images, &$files) {
            $uploadData = [
                'id' => $upload->id,
                'file_name' => basename($upload->file_path),
                    'file_url' => str_starts_with($upload->mime, 'image/')
                        ? asset('storage/' . ltrim($upload->file_path, '/'))
                        : (request()->getSchemeAndHttpHost() . route('medical-record.attachment.download', ['id' => $upload->id], false)),
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
                'doctor_name' => $this->doctor?->user
                    ? __('enums.title.doctor_prefix') . ' ' . $this->doctor->user->full_name
                    : null,
                'care_provider_id' => $this->care_provider_id,
                'care_provider_name' => $this->careProvider?->user?->full_name,
                'care_provider_type' => $this->careProvider?->type,
                'care_provider_type_label' => \App\Support\Locale::label('service_type', $this->careProvider?->type),
                'diagnosis' => $this->diagnosis,
                'treatment_plan' => $this->treatment_plan,
                'chronic_diseases' => $this->chronic_diseases,
                'pre_existing_conditions' => $this->pre_existing_conditions,
                'other_conditions' => $this->other_conditions,
                'previous_surgeries' => $this->previous_surgeries,
                'allergies' => $this->allergies,
                'current_medications' => $this->current_medications,
                'is_pregnant' => (bool) $this->is_pregnant,
                'images' => $images,
                'files' => $files,
                'created_at' => $this->created_at?->toDateTimeString(),
                'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
