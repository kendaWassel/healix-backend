<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyResetOtpRequest;
use App\Services\Auth\PasswordResetOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * OTP-based password reset.
 *
 *   1. POST auth/forgot-password    email               -> code emailed
 *   2. POST auth/verify-reset-otp   email + otp         -> reset_token
 *   3. POST auth/reset-password     email + reset_token -> password changed
 *
 * All security decisions live in PasswordResetOtpService; this class only maps
 * outcomes onto HTTP and keeps failures indistinguishable where they need to be.
 */
class PasswordResetController extends Controller
{
    public function __construct(
        protected PasswordResetOtpService $otpService
    ) {}

    /**
     * Step 1 — request a code.
     *
     * Always the same 200 response. Whether the address is registered, whether
     * a resend cooldown applied, and whether SMTP succeeded are logged but never
     * returned, so the endpoint cannot be used to enumerate accounts.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->otpService->request($request->validated('email'));

        return response()->json([
            'status' => 'success',
            'message' => __('auth.forgot_password_sent'),
        ]);
    }

    /**
     * Step 2 — verify the code and obtain a reset token.
     *
     * Unlike step 1 this reports real failures: the caller already proved they
     * control the inbox, and "wrong code" vs "expired code" is the difference
     * between retyping and requesting a new one. It still never distinguishes
     * a missing account from a wrong code.
     */
    public function verifyOtp(VerifyResetOtpRequest $request): JsonResponse
    {
        $email = $request->validated('email');

        $outcome = $this->otpService->verify($email, $request->validated('otp'));

        if ($outcome['result'] === PasswordResetOtpService::RESULT_OK) {
            return response()->json([
                'status' => 'success',
                'message' => __('auth.otp_verified'),
                'data' => [
                    'reset_token' => $outcome['reset_token'],
                    'expires_in' => $outcome['expires_in'],
                ],
            ]);
        }

        Log::warning('Password reset OTP verification failed', [
            'email' => $email,
            'result' => $outcome['result'],
        ]);

        return response()->json([
            'status' => 'error',
            'message' => $this->otpFailureMessage($outcome['result']),
        ], $outcome['result'] === PasswordResetOtpService::RESULT_TOO_MANY_ATTEMPTS ? 429 : 422);
    }

    /**
     * Step 3 — set the new password.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');

        $result = $this->otpService->reset(
            $email,
            $request->validated('reset_token'),
            $request->validated('password'),
        );

        if ($result === PasswordResetOtpService::RESULT_OK) {
            return response()->json([
                'status' => 'success',
                'message' => __('auth.password_reset_success'),
            ]);
        }

        Log::warning('Password reset failed', [
            'email' => $email,
            'result' => $result,
        ]);

        return response()->json([
            'status' => 'error',
            'message' => __('auth.reset_token_invalid'),
        ], 422);
    }

    protected function otpFailureMessage(string $result): string
    {
        return match ($result) {
            PasswordResetOtpService::RESULT_EXPIRED => __('auth.otp_expired'),
            PasswordResetOtpService::RESULT_TOO_MANY_ATTEMPTS => __('auth.otp_too_many_attempts'),
            default => __('auth.otp_invalid'),
        };
    }
}
