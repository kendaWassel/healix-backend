<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

class DeliveryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, Delivery $delivery): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'delivery' && ($user->delivery->id ?? null) === $delivery->id) {
            return true;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Delivery $delivery): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'delivery' && ($user->delivery->id ?? null) === $delivery->id) {
            return true;
        }
        return false;
    }

    public function delete(User $user, Delivery $delivery): bool
    {
        return $user->role === 'admin';
    }
}

