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
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user = Auth::user();

        if ($user->email_verified_at === null) {
            return response()->json([
                'message' => 'Email not verified',
                'error' => 'Please verify your email address before accessing this resource',
            ], 403);
        }

        return $next($request);
    }
}
