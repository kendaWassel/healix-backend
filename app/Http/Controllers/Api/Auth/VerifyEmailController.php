<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
    use App\Models\User;
use App\Mail\VerificationEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Events\Verified;

class VerifyEmailController extends Controller
{
    //send email verification to user
    public static function sendVerificationEmail(User $user)
    {
        

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified'
            ], 400);
        }

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        try {
            Mail::to($user->email)->send(new VerificationEmail($user, $verificationUrl));

            return response()->json([
                'message' => 'Verification email sent successfully'
                
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send verification email',
                'error' => $e->getMessage()
            ], 500);
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
