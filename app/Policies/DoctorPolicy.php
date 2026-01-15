<?php

namespace App\Policies;

use App\Models\Doctor;
use App\Models\User;

class DoctorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, Doctor $doctor): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'doctor' && ($user->doctor->id ?? null) === $doctor->id) {
            return true;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Doctor $doctor): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'doctor' && ($user->doctor->id ?? null) === $doctor->id) {
            return true;
        }
        return false;
    }

    public function delete(User $user, Doctor $doctor): bool
    {
        return $user->role === 'admin';
    }
}

