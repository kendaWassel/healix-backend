<?php

namespace App\Policies;

use App\Models\Consultation;
use App\Models\User;

class ConsultationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if  ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'doctor' || $user->role === 'patient') {
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Consultation $consultation): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'patient' && $consultation->patient->user_id === $user->id) {
            return true;
        }

        if ($user->role === 'doctor' && $consultation->doctor_id === $user->doctor->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     *
     * Real bug found and fixed here: this returned `role === 'doctor'`,
     * backwards from every other method in this policy (view/update both
     * treat the booking patient as the owning actor) and from the real
     * booking route (ConsultationController::bookConsultation sits behind
     * role:patient in routes/api/patient.php, not role:doctor) — patients
     * book consultations, not doctors. Currently dead code (nothing in the
     * app calls authorize('create', Consultation::class) yet), so this had
     * no live effect, but tests/Unit/ConsultationTest.php's own
     * patient_can_create_consultations/doctor_cannot_create_consultations
     * assertions (which encode the correct, intended rule) were failing
     * against it before this fix.
     */
    public function create(User $user): bool
    {
        return $user->role === 'patient';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Consultation $consultation): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'doctor' && $consultation->doctor_id === $user->doctor->id) {
            return true;
        }

        if ($user->role === 'patient' && $consultation->patient->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Consultation $consultation): bool
    {
        return $user->role === 'admin';
    }
}
