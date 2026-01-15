<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;

class PrescriptionPolicy
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
    public function view(User $user, Prescription $prescription): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'patient' && $prescription->patient_id === $user->patient->id) {
            return true;
        }

        if ($user->role === 'doctor' && $prescription->doctor_id === $user->doctor->id) {
            return true;
        }

        if ($user->role === 'pharmacist' && $prescription->pharmacist_id === $user->pharmacist->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'doctor' || $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Prescription $prescription): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'doctor' && $prescription->doctor_id === $user->doctor->id) {
            return true;
        }

        if ($user->role === 'pharmacist' && $prescription->pharmacist_id === $user->pharmacist->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Prescription $prescription): bool
    {
        return $user->role === 'admin';
    }
}
