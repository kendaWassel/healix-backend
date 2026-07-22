<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyResetOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `digits` keeps the code numeric and exactly the configured length, so a
     * malformed value is rejected before it can burn one of the user's limited
     * verification attempts.
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string|email|max:255',
            'otp' => 'required|string|digits:' . (int) config('password_otp.length', 6),
        ];
    }

    /**
     * Mobile keypads and Arabic locales can submit the code with spaces or as
     * Arabic-Indic digits (٠١٢…). Normalize before validation so a legitimate
     * user is not blocked by how their keyboard renders numerals.
     */
    protected function prepareForValidation(): void
    {
        $otp = $this->input('otp');

        if (! is_string($otp)) {
            return;
        }

        $western = '0123456789';

        $this->merge([
            'otp' => str_replace(
                array_merge(
                    [' ', '-'],
                    mb_str_split('٠١٢٣٤٥٦٧٨٩'),   // Arabic-Indic
                    mb_str_split('۰۱۲۳۴۵۶۷۸۹'),   // Extended Arabic-Indic (Persian/Urdu)
                ),
                array_merge(
                    ['', ''],
                    mb_str_split($western),
                    mb_str_split($western),
                ),
                $otp
            ),
        ]);
    }

    public function messages(): array
    {
        return [
            'email.required' => __('requests.verify_reset_otp.email_required'),
            'email.email' => __('requests.verify_reset_otp.email_email'),
            'otp.required' => __('requests.verify_reset_otp.otp_required'),
            'otp.digits' => __('requests.verify_reset_otp.otp_digits', [
                'digits' => (int) config('password_otp.length', 6),
            ]),
        ];
    }
}
