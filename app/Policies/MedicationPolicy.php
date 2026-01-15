<?php

namespace App\Policies;

use App\Models\Medication;
use App\Models\User;

class MedicationPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'pharmacist', 'doctor']);
    }

    public function view(User $user, Medication $medication): bool
    {
        return in_array($user->role, ['admin', 'pharmacist', 'doctor']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'pharmacist']);
    }

    public function update(User $user, Medication $medication): bool
    {
        return in_array($user->role, ['admin', 'pharmacist']);
    }

    public function delete(User $user, Medication $medication): bool
    {
        return $user->role === 'admin';
    }
}

