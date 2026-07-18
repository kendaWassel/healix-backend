<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
    use App\Models\User;
use App\Mail\VerificationEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Events\Verified;

class VerifyEmailController extends Controller
{
    /**
     * Send the verification email to the user.
     *
     * Only ever called from AuthService::register() (not a route handler), so
     * this reports success/failure as a boolean instead of an HTTP response
     * that nothing actually reads.
     *
     * @return bool True if the email was sent, false if it was skipped
     *              (already verified) or failed.
     */
    public static function sendVerificationEmail(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        // Generate a signed verification URL valid for 60 minutes
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        try {
            Mail::to($user->email)->send(new VerificationEmail($user, $verificationUrl));

            return true;
        } catch (\Exception $e) {
            // This used to be swallowed silently (returned in an HTTP response
            // nothing read), so mail failures never showed up anywhere.
            Log::error('Failed to send verification email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function verify(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (!$request->hasValidSignature()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Invalid or expired verification link'], 400);
            }
            return redirect(env('FRONTEND_URL') . '?verified=false&message=Invalid+or+expired+verification+link');
        }

        if (!hash_equals((string) $hash, sha1($user->email))) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Invalid verification link'], 400);
            }
            return redirect(env('FRONTEND_URL') . '?verified=false&message=Invalid+verification+link');
        }

        if ($user->hasVerifiedEmail()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Email already verified'], 400);
            }
            return redirect(env('FRONTEND_URL') . '?verified=true&message=Email+already+verified');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));

            // Patients don't need admin vetting (unlike doctors/pharmacists/
            // care providers/delivery, who require manual license/ID review),
            // so their email verification is sufficient to activate the account.
            if ($user->role === 'patient' && $user->status !== 'approved') {
                $user->status = 'approved';
                $user->is_active = true;
                $user->approved_at = now();
                $user->admin_note = 'Auto-approved: patient email verification';
                $user->save();
            }

            $token = $user->createToken('Email Verification Token')->plainTextToken;

            if ($request->wantsJson()) {
                return response()->json([
                    'verified' => true,
                    'token' => $token,
                    'email' => $user->email,
                    'message' => 'Email verified successfully',
                ]);
            }

            return redirect(env('FRONTEND_URL') . 'api/auth/login?' . http_build_query([
                'verified' => 'true',
                'token' => $token,
                'email' => $user->email,
                'message' => 'Email verified successfully'
            ]));
        }

        if ($request->wantsJson()) {
            return response()->json([
                'verified' => false,
                'message' => 'Failed to verify email',
            ], 500);
        }

        return redirect(env('FRONTEND_URL') . 'api/auth/login?' . http_build_query([
            'verified' => 'false',
            'message' => 'Failed to verify email'
        ]));
    }

    
}
