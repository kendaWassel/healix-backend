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
            // Optional: omitted while the driver is idle/available (not on an
            // active task) and just reporting their live position so the
            // nearest-driver search can find them.
            'task_id' => ['sometimes', 'integer', 'exists:delivery_tasks,id'],
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
            'task_id.required' => __('requests.delivery_location.task_id_required'),
            'task_id.integer' => __('requests.delivery_location.task_id_integer'),
            'task_id.exists' => __('requests.delivery_location.task_id_exists'),
            'latitude.required' => __('requests.delivery_location.latitude_required'),
            'latitude.numeric' => __('requests.delivery_location.latitude_numeric'),
            'latitude.between' => __('requests.delivery_location.latitude_between'),
            'longitude.required' => __('requests.delivery_location.longitude_required'),
            'longitude.numeric' => __('requests.delivery_location.longitude_numeric'),
            'longitude.between' => __('requests.delivery_location.longitude_between'),
        ];
    }
}
