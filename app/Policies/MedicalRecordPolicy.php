<?php

namespace App\Policies;

use App\Models\Consultation;
use App\Models\MedicalRecord;
use App\Models\User;

class MedicalRecordPolicy
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
    public function view(User $user, MedicalRecord $medicalRecord): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'patient' && $medicalRecord->patient_id === $user->patient->id) {
            return true;
        }

        if ($user->role === 'doctor') {
            if ($medicalRecord->doctor_id === $user->doctor->id) {
                return true;
            }

            // A doctor who has an actual (booked) consultation with this
            // patient can view their record even when a DIFFERENT doctor
            // authored it — a patient's medical history needs to be visible
            // to whichever doctor is now treating them, not just the one who
            // happened to write the most recent entry. Mirrors
            // LabAnalysisController::myPatientsLabAnalyses's own "my
            // patients" scoping (Consultation doctor_id/patient_id), the
            // established doctor-patient relationship this app already uses
            // elsewhere for the same kind of check.
            return Consultation::where('doctor_id', $user->doctor->id)
                ->where('patient_id', $medicalRecord->patient_id)
                ->exists();
        }

        if ($user->role === 'care_provider' && $medicalRecord->care_provider_id === $user->careProvider->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'doctor' || $user->role === 'care_provider' || $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MedicalRecord $medicalRecord): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'doctor' && $medicalRecord->doctor_id === $user->doctor->id) {
            return true;
        }

        if ($user->role === 'care_provider' && $medicalRecord->care_provider_id === $user->careProvider->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MedicalRecord $medicalRecord): bool
    {
        return $user->role === 'admin';
    }
}
