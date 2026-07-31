<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'stars' => 'required|integer|min:1|max:5',
        ];
    }

    public function messages(): array
    {
        return [
            'stars.required' => __('requests.rate.stars_required'),
            'stars.integer' => __('requests.rate.stars_integer'),
            'stars.min' => __('requests.rate.stars_min'),
            'stars.max' => __('requests.rate.stars_max'),

        ];
    }
}