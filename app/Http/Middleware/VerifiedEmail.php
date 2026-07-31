<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifiedEmail
{
    /**
     * Handle email verification check.
     * Ensures user has verified their email before accessing protected routes.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return response()->json(['message' => __('auth.unauthenticated')], 401);
        }

        $user = Auth::user();

        if ($user->email_verified_at === null) {
            return response()->json([
                'message' => __('auth.email_not_verified'),
                'error' => __('auth.email_verification_required'),
            ], 403);
        }

        return $next($request);
    }
}
