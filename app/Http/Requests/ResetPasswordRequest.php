<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `reset_token` is the short-lived credential handed out by
     * POST /api/auth/verify-reset-otp — not the OTP itself, which is already
     * spent by that point.
     *
     * `confirmed` expects a matching `password_confirmation` field. min:8
     * mirrors registration, so a password chosen through a reset can never be
     * weaker than one chosen at signup.
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string|email|max:255',
            'reset_token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('requests.reset_password.email_required'),
            'email.email' => __('requests.reset_password.email_email'),
            'reset_token.required' => __('requests.reset_password.token_required'),
            'password.required' => __('requests.reset_password.password_required'),
            'password.min' => __('requests.reset_password.password_min'),
            'password.confirmed' => __('requests.reset_password.password_confirmed'),
        ];
    }
}
