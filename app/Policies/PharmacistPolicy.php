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

    public function view(User $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'pharmacist') {
            return true;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'pharmacist') {
            return true;
        }
        return false;
    }

    public function delete(User $user): bool
    {
        if ($user->role === 'admin') {    
            return true;
        }
        if ($user->role === 'pharmacist') {
            if ($user->pharmacist->id === $user->id) {
                return false;   
            }
            return true;
        }
        return false;
    }

  
}

