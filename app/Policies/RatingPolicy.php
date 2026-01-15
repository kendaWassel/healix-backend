<?php

namespace App\Policies;

use App\Models\Rating;
use App\Models\User;

class RatingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, Rating $rating): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'patient' && $rating->patient_id === ($user->patient->id ?? null)) {
            return true;
        }
        if ($rating->target_type === 'doctor' && $user->role === 'doctor' && $rating->target_id === ($user->doctor->id ?? null)) {
            return true;
        }
        if ($rating->target_type === 'care_provider' && $user->role === 'care_provider' && $rating->target_id === ($user->careProvider->id ?? null)) {
            return true;
        }
        if ($rating->target_type === 'pharmacist' && $user->role === 'pharmacist' && $rating->target_id === ($user->pharmacist->id ?? null)) {
            return true;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === 'patient';
    }

    public function update(User $user, Rating $rating): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        return $user->role === 'patient' && $rating->patient_id === ($user->patient->id ?? null);
    }

    public function delete(User $user, Rating $rating): bool
    {
        return $user->role === 'admin';
    }
}

