<?php

namespace App\Policies;

use App\Models\CareProvider;
use App\Models\User;

class CareProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, CareProvider $careProvider): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'care_provider' && ($user->careProvider->id ?? null) === $careProvider->id) {
            return true;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, CareProvider $careProvider): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'care_provider' && ($user->careProvider->id ?? null) === $careProvider->id) {
            return true;
        }
        return false;
    }

    public function delete(User $user, CareProvider $careProvider): bool
    {
        return $user->role === 'admin';
    }
}

