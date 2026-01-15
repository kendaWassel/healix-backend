<?php

namespace App\Policies;

use App\Models\Consultation;
use App\Models\User;

class ConsultationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Consultation $consultation): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'patient' && $consultation->patient->user_id === $user->id) {
            return true;
        }

        if ($user->role === 'doctor' && $consultation->doctor_id === $user->doctor->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'patient' || $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Consultation $consultation): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'doctor' && $consultation->doctor_id === $user->doctor->id) {
            return true;
        }

        if ($user->role === 'patient' && $consultation->patient->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Consultation $consultation): bool
    {
        return $user->role === 'admin';
    }
}
