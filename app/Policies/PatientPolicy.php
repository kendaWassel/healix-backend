<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin' || $user->role=='patient';
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Patient $patient): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'patient' && $user->id === $patient->user_id) {
            return true;
        }

        if ($user->role === 'doctor') {
            return $patient->consultations()->where('doctor_id', $user->doctor->id)->exists();
        }

        if ($user->role === 'care_provider') {
            return $patient->homeVisits()->where('care_provider_id', $user->careProvider->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'patient';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Patient $patient): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'patient' && $user->id === $patient->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Patient $patient): bool
    {
        return $user->role === 'admin';
    }
}
