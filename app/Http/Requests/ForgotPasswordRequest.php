<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Note there is deliberately no `exists:users,email` rule.
     *
     * Validating existence here would make the endpoint answer "is this email
     * registered?" through the 422 response — exactly the user enumeration the
     * generic success message is designed to prevent.
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string|email|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('requests.forgot_password.email_required'),
            'email.email' => __('requests.forgot_password.email_email'),
            'email.max' => __('requests.forgot_password.email_max'),
        ];
    }
}
