<?php

namespace App\Policies;

use App\Models\FirstAid;
use App\Models\User;

class FirstAidPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, FirstAid $firstAid): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, FirstAid $firstAid): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, FirstAid $firstAid): bool
    {
        return $user->role === 'admin';
    }
}

