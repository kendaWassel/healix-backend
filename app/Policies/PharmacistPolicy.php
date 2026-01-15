<?php

namespace App\Policies;

use App\Models\Pharmacist;
use App\Models\User;

class PharmacistPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, Pharmacist $pharmacist): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'pharmacist' && ($user->pharmacist->id ?? null) === $pharmacist->id) {
            return true;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Pharmacist $pharmacist): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'pharmacist' && ($user->pharmacist->id ?? null) === $pharmacist->id) {
            return true;
        }
        return false;
    }

    public function delete(User $user, Pharmacist $pharmacist): bool
    {
        return $user->role === 'admin';
    }
}

