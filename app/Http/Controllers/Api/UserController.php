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
            'message' => __('messages.profile_retrieved'),
            'data' => $this->profilePayload($user),
        ]);
    }

    /**
     * Shared profile shape returned by both getProfile and updateProfile.
     *
     * birth_date and gender live on the patient profile, not the user row —
     * that is why the account screen showed them empty before: they were never
     * in the payload. gender_label is an additive localized display value; the
     * raw gender key is unchanged so existing clients keep working.
     */
    protected function profilePayload(User $user): array
    {
        $patient = $user->patient;
        $gender = $patient->gender === 'male' ? __('personal.gender_male') : ($patient->gender === 'female' ? __('personal.gender_female') : __('personal.gender_other'));

        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'birth_date' => $patient?->birth_date,
            'gender' => $gender,
            'address' => $patient?->address,
            'role' => $user->role,
            'email_verified' => $user->email_verified_at ? true : false,
            'preferred_locale' => $user->preferredLocale(),
        ];
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
            'birth_date' => 'sometimes|nullable|date|before:today',
            'gender' => 'sometimes|nullable|in:male,female',
            'preferred_locale' => 'sometimes|string|in:' . implode(',', config('localization.supported')),
        ]);

        $user = Auth::user();

        // Update user fields
        if ($request->has('full_name')) {
            $user->full_name = $request->full_name;
        }
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }
        // Lets the user pin the language used for queued mail/SMS, independently
        // of whatever Accept-Language the current device happens to send.
        if ($request->has('preferred_locale')) {
            $user->preferred_locale = $request->preferred_locale;
        }
        $user->save();

        // address / birth_date / gender live on the patient profile.
        $patient = $user->patient;
        if ($patient) {
            foreach (['address', 'birth_date', 'gender'] as $field) {
                if ($request->has($field)) {
                    $patient->{$field} = $request->input($field);
                }
            }
            $patient->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => __('messages.profile_updated'),
            'data' => [
                // Kept nested under `user` to preserve the existing response shape.
                'user' => $this->profilePayload($user->refresh()),
            ],
        ]);
    }
}