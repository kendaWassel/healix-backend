<?php

namespace App\Policies;

use App\Models\DeliveryTask;
use App\Models\User;

class DeliveryTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'delivery';
    }

    public function view(User $user, DeliveryTask $deliveryTask): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'delivery' && $user->delivery && $user->delivery->id === $deliveryTask->delivery_id) {
            return true;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'delivery';
    }

    public function update(User $user, DeliveryTask $deliveryTask): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'delivery' && $user->delivery && $user->delivery->id === $deliveryTask->delivery_id) {
            return true;
        }
        return false;
    }

    public function delete(User $user, DeliveryTask $deliveryTask): bool
    {
        return $user->role === 'admin';
    }
}
