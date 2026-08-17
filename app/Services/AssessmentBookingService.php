<?php

namespace App\Services;

use App\Models\Assessment;
use Carbon\Carbon;

/**
 * Composes the single "assessment result + specialty + available doctors +
 * slots" payload the patient app needs to offer booking after an assessment
 * finishes (GET /patient/assessments/{id}/booking). Pure composition over
 * SpecializationResolver + the existing DoctorService methods — no new
 * doctor/slot logic here.
 */
class AssessmentBookingService
{
    public function __construct(
        protected SpecializationResolver $resolver,
        protected DoctorService $doctorService,
    ) {}

    public function getBookingOptions(Assessment $assessment, ?string $date = null): array
    {
        $date = $date ?? Carbon::now()->toDateString();

        $specialization = $assessment->specialization
            ?? $this->resolver->resolve($assessment->recommended_specialty);

        $doctors = [];
        if ($specialization !== null) {
            $result = $this->doctorService->getDoctorsBySpecialization($specialization->id, 50);
            $doctors = collect($result['doctors'])
                ->map(function (array $doctor) use ($date) {
                    $slots = $this->doctorService->getAvailableSlots($doctor['id'], $date);
                    $doctor['available_slots'] = $slots['available_slots'];
                    return $doctor;
                })
                ->values()
                ->all();
        }

        return [
            'assessment' => [
                'id' => $assessment->id,
                'status' => $assessment->status,
                'triage' => $assessment->triage,
                'recommended_specialty' => $assessment->recommended_specialty,
                'possible_diseases' => $assessment->possible_diseases,
                'emergency_detected' => $assessment->emergency_detected,
                'created_at' => $assessment->created_at?->toIso8601String(),
            ],
            'specialty' => [
                'id' => $specialization?->id,
                'code' => $assessment->specialty_code ?? $specialization?->code,
                'name_ar' => $assessment->specialty_name_ar ?? $specialization?->name_ar,
            ],
            'date' => $date,
            'can_book' => $specialization !== null && count($doctors) > 0,
            'doctors' => $doctors,
        ];
    }
}
