<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'category' => 'required|string|in:certificate,report,document,prescription,profile',
        ];

        if ($this->hasFile('file')) {
            $rules['file'] = 'required|file|max:10240|mimes:pdf,doc,docx,txt'; // 10MB for general files
        }

        if ($this->hasFile('image')) {
            $rules['image'] = 'required|image|mimes:jpeg,png,jpg,gif|max:5120'; // 5MB for images
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'file.required' => __('requests.upload.file_required'),
            'file.file' => __('requests.upload.file_file'),
            'file.max' => __('requests.upload.file_max'),
            'image.required' => __('requests.upload.image_required'),
            'image.image' => __('requests.upload.image_image'),
            'image.mimes' => __('requests.upload.image_mimes'),
            'image.max' => __('requests.upload.image_max'),
            'category.required' => __('requests.upload.category_required'),
            'category.string' => __('requests.upload.category_string'),
            'category.in' => __('requests.upload.category_in'),
        ];
    }
}