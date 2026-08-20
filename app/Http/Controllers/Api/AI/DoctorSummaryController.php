<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Models\DoctorSummary;
use App\Models\Patient;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

/**
 * Doctor-facing view of the Healix/AI-generated report for a patient —
 * previously nonexistent: this class was an empty, unrouted stub, and
 * ConversationPolicy hard-codes patient-only ownership on the
 * conversation/report data itself, so no doctor could see any Healix
 * output at all before this.
 *
 * Deliberately scoped to "one patient this doctor has a real Consultation
 * with" (DoctorSummaryPolicy::view), never a general "all patients in my
 * specialty" browse — a doctor with no booked relationship to a patient
 * gets a 403, the same as MedicalRecordController::viewDetails now
 * enforces for medical records.
 */
class DoctorSummaryController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /doctor/patients/{patient_id}/doctor-summaries
     */
    public function forPatient(int $patientId): JsonResponse
    {
        $patient = Patient::with('user')->find($patientId);
        if (!$patient) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.patient_not_found'),
            ], 404);
        }

        $summaries = DoctorSummary::where('patient_id', $patientId)
            ->latest()
            ->get();

        // Authorize against the first real row when one exists; otherwise
        // an unsaved stand-in carrying just patient_id, so a doctor with a
        // real consultation but no report yet (e.g. the assessment hasn't
        // finished, or no Healix conversation reached a diagnosis) still
        // gets the same relationship check rather than an unguarded read —
        // same pattern as MedicalRecordController::viewDetails's own fix.
        $this->authorize('view', $summaries->first() ?? new DoctorSummary(['patient_id' => $patientId]));

        return response()->json([
            'status' => 'success',
            'data' => [
                'patient_id' => $patient->id,
                'patient_name' => $patient->user?->full_name,
                'doctor_summaries' => $summaries->map(fn (DoctorSummary $summary) => [
                    'id' => $summary->id,
                    'assessment_id' => $summary->assessment_id,
                    'conversation_id' => $summary->conversation_id,
                    'summary' => $summary->summary,
                    'status' => $summary->status,
                    'created_at' => $summary->created_at?->toIso8601String(),
                    'updated_at' => $summary->updated_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }
}
