<?php

namespace App\Policies;

use App\Models\HomeVisit;
use App\Models\User;

class HomeVisitPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'care_provider']);
    }

    public function view(User $user, HomeVisit $homeVisit): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'patient' && $homeVisit->patient_id === ($user->patient->id ?? null)) {
            return true;
        }
        if ($user->role === 'doctor' && $homeVisit->doctor_id === ($user->doctor->id ?? null)) {
            return true;
        }
        if ($user->role === 'care_provider' && $homeVisit->care_provider_id === ($user->careProvider->id ?? null)) {
            return true;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['patient', 'doctor', 'care_provider']);
    }
    public function createFollowUp(User $user): bool
    {
        return $user->role === 'care_provider';
    }
    

    public function update(User $user, HomeVisit $homeVisit): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'care_provider' && $homeVisit->care_provider_id === ($user->careProvider->id ?? null)) {
            return true;
        }
        if ($user->role === 'doctor' && $homeVisit->doctor_id === ($user->doctor->id ?? null)) {
            return true;
        }
        if ($user->role === 'patient' && $homeVisit->patient_id === ($user->patient->id ?? null)) {
            return true;
        }
        return false;
    }

    public function delete(User $user, HomeVisit $homeVisit): bool
    {
        return $user->role === 'admin';
    }
}

