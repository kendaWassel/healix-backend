<?php

namespace App\Policies;

use App\Models\MedicalRecord;
use App\Models\User;

class MedicalRecordPolicy
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
    public function view(User $user, MedicalRecord $medicalRecord): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'patient' && $medicalRecord->patient_id === $user->patient->id) {
            return true;
        }

        if ($user->role === 'doctor' && $medicalRecord->doctor_id === $user->doctor->id) {
            return true;
        }

        if ($user->role === 'care_provider' && $medicalRecord->care_provider_id === $user->careProvider->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'doctor' || $user->role === 'care_provider' || $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MedicalRecord $medicalRecord): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'doctor' && $medicalRecord->doctor_id === $user->doctor->id) {
            return true;
        }

        if ($user->role === 'care_provider' && $medicalRecord->care_provider_id === $user->careProvider->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MedicalRecord $medicalRecord): bool
    {
        return $user->role === 'admin';
    }
}
