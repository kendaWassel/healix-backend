<?php

namespace App\Policies;

use App\Models\Consultation;
use App\Models\DoctorSummary;
use App\Models\User;

/**
 * A doctor may see a DoctorSummary (the Healix/AI-generated report for a
 * patient) when they have an actual, booked Consultation with that
 * patient — never merely because doctor_id happens to be stamped on this
 * specific row. doctor_id there is only filled in once THIS doctor's own
 * booking links it (ConsultationService::linkAssessmentToBooking); a
 * report from an earlier conversation, before this doctor was ever
 * involved, should still be visible once the relationship exists. Same
 * relationship rule as MedicalRecordPolicy::view's own fix, and the same
 * Consultation(doctor_id, patient_id) scoping
 * LabAnalysisController::myPatientsLabAnalyses already uses.
 */
class DoctorSummaryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'doctor';
    }

    public function view(User $user, DoctorSummary $doctorSummary): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'doctor') {
            return Consultation::where('doctor_id', $user->doctor->id)
                ->where('patient_id', $doctorSummary->patient_id)
                ->exists();
        }

        return false;
    }
}
