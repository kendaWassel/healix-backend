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
            'stars.required' => 'Rating stars are required.',
            'stars.integer' => 'Stars must be an integer.',
            'stars.min' => 'Stars must be at least 1.',
            'stars.max' => 'Stars must be at most 5.',

        ];
    }
}