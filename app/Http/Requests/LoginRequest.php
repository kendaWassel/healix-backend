<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('requests.login.email_required'),
            'email.email' => __('requests.login.email_email'),
            'email.max' => __('requests.login.email_max'),
            'password.required' => __('requests.login.password_required'),
            'password.min' => __('requests.login.password_min'),
        ];
    }

}