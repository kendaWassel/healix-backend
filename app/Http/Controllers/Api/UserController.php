<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Get current user profile
     */
    public function getProfile(Request $request)
    {
        $user = Auth::user();

        // Get patient profile if exists
        $patient = $user->patient;

        return response()->json([
            'status' => 'success',
            'message' => 'Profile retrieved successfully',
            'data' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $patient ? $patient->address : null,
                'role' => $user->role,
                'email_verified' => $user->email_verified_at ? true : false,
            ]
        ]);
    }

    /**
     * Update current user profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|max:500',
        ]);

        $user = Auth::user();

        // Update user fields
        if ($request->has('full_name')) {
            $user->full_name = $request->full_name;
        }
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }
        $user->save();

        // Update patient address if exists
        if ($request->has('address')) {
            $patient = $user->patient;
            if ($patient) {
                $patient->address = $request->address;
                $patient->save();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $user->patient ? $user->patient->address : null,
                    'role' => $user->role,
                    'email_verified' => $user->email_verified_at ? true : false,
                ]
            ]
        ]);
    }
}