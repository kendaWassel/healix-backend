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
            'file.required' => 'File is required.',
            'file.file' => 'The uploaded item must be a file.',
            'file.max' => 'File size must not exceed 10MB.',
            'image.required' => 'Image is required.',
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'Image must be jpeg, png, jpg, or gif.',
            'image.max' => 'Image size must not exceed 5MB.',
            'category.required' => 'Category is required.',
            'category.string' => 'Category must be a string.',
            'category.in' => 'Category must be one of: certificate, report, document, prescription, profile.',
        ];
    }
}