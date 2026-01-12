<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role)
    {
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        $user = Auth::user();

        // التحقق من الدور
        if ($user->role !== $role) {
            return response()->json([
                'message' => 'Forbidden: role mismatch'
            ], 403);
        }

        // التحقق من تفعيل الإيميل والموافقة من الأدمن
        if ($user->email_verified_at === null || $user->status !== 'approved' || $user->is_active !== true) {
            return response()->json([
                'message' => 'Account not fully activated',
                'email_verified' => $user->email_verified_at !== null,
                'status' => $user->status,
                'rejection_reason' => $user->rejection_reason,
            ], 403);
        }

        return $next($request);
    }
}
