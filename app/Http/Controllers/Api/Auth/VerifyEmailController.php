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

        // Generate a signed verification URL valid for 60 minutes.
        // `lang` is included so the landing page (opened from a mail client
        // that cannot send Accept-Language) follows the user's preference.
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
                'lang' => $user->preferredLocale(),
            ]
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

    /**
     * Browser landing page after email verification. Tries to open the
     * Healix app via deep link; shows a tap-to-open fallback if that fails.
     */
    public function openApp(Request $request)
    {
        return response()
            ->view('auth.verify-email', [
                'token' => $request->query('token'),
                'role' => $request->query('role'),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function verify(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (!$request->hasValidSignature()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => __('auth.verification_link_expired')], 400);
            }
            return $this->redirectToLanding($user);
        }

        if (!hash_equals((string) $hash, sha1($user->email))) {
            if ($request->wantsJson()) {
                return response()->json(['message' => __('auth.verification_link_invalid')], 400);
            }
            return $this->redirectToLanding($user);
        }

        if ($user->hasVerifiedEmail()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => __('auth.email_already_verified')], 400);
            }

            return $this->redirectToApp($user);
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
                    'role' => $this->clientRole($user),
                    'email' => $user->email,
                    'message' => __('auth.email_verified'),
                ]);
            }

            return $this->redirectToLanding($user, $token);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'verified' => false,
                'message' => __('auth.verification_failed'),
            ], 500);
        }

        return $this->redirectToLanding($user);
    }

    private function redirectToApp(User $user)
    {
        $token = $user->createToken('Email Verification Token')->plainTextToken;

        return $this->redirectToLanding($user, $token);
    }

    private function redirectToLanding(User $user, ?string $token = null)
    {
        return redirect()->route('verify-email', array_filter([
            'token' => $token,
            'role' => $token ? $this->clientRole($user) : null,
            'lang' => $user->preferredLocale(),
        ]));
    }

    /**
     * Role string the mobile client navigates on (see VerifyEmailScreen).
     * Users stored as care_provider are nurse or physiotherapist there.
     */
    private function clientRole(User $user): string
    {
        if ($user->role !== 'care_provider') {
            return (string) $user->role;
        }

        $type = $user->careProvider?->type;

        return in_array($type, ['nurse', 'physiotherapist'], true)
            ? $type
            : 'care_provider';
    }
}
