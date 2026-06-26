<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryLocationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'task_id' => ['required', 'integer', 'exists:delivery_tasks,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'task_id.required' => 'Task id is required.',
            'task_id.integer' => 'Task id must be a valid integer.',
            'task_id.exists' => 'The specified delivery task does not exist.',
            'latitude.required' => 'Latitude is required.',
            'latitude.numeric' => 'Latitude must be a numeric value.',
            'latitude.between' => 'Latitude must be between -90 and 90.',
            'longitude.required' => 'Longitude is required.',
            'longitude.numeric' => 'Longitude must be a numeric value.',
            'longitude.between' => 'Longitude must be between -180 and 180.',
        ];
    }
}
