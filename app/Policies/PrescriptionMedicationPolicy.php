<?php

namespace App\Policies;

use App\Models\PrescriptionMedication;
use App\Models\User;

class PrescriptionMedicationPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'pharmacist', 'doctor']);
    }

    public function view(User $user, PrescriptionMedication $pm): bool
    {
        if (in_array($user->role, ['admin', 'pharmacist', 'doctor'])) {
            return true;
        }
        if ($user->role === 'patient' && $pm->prescription && $pm->prescription->patient_id === ($user->patient->id ?? null)) {
            return true;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'pharmacist', 'doctor']);
    }

    public function update(User $user, PrescriptionMedication $pm): bool
    {
        return in_array($user->role, ['admin', 'pharmacist', 'doctor']);
    }

    public function delete(User $user, PrescriptionMedication $pm): bool
    {
        return $user->role === 'admin';
    }
}

