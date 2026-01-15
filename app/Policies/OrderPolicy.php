<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
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
    public function view(User $user, Order $order): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'patient' && $order->patient_id === $user->patient->id) {
            return true;
        }

        if ($user->role === 'pharmacist' && $order->pharmacist_id === $user->pharmacist->id) {
            return true;
        }

        if ($user->role === 'delivery' && $order->deliveryTask && $order->deliveryTask->delivery_id === $user->delivery->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Order $order): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'pharmacist' && $order->pharmacist_id === $user->pharmacist->id) {
            return true;
        }

        if ($user->role === 'delivery' && $order->deliveryTask && $order->deliveryTask->delivery_id === $user->delivery->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->role === 'admin';
    }
}
