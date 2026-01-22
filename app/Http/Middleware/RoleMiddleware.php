<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle role-based access control.
     * Supports single role or multiple comma-separated roles.
     * 
     * Usage:
     * - Single role: role:admin
     * - Multiple roles: role:doctor,nurse,physiotherapist
     */
    public function handle(Request $request, Closure $next, string $roles)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Split roles by comma and trim whitespace
        $allowedRoles = array_map('trim', explode(',', $roles));

        if (!in_array(Auth::user()->role, $allowedRoles)) {
            return response()->json([
                'message' => 'Forbidden: Access denied',
                'required_role' => count(value: $allowedRoles) > 1 ? $allowedRoles : $allowedRoles[0],
            ], 403);
        }

        return $next($request);
    }
}
