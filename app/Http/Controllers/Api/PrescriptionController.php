<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class PrescriptionController extends Controller
{
    use AuthorizesRequests;

    /**
     * Export a prescription as a FHIR-compatible MedicationRequest Bundle.
     *
     * Lightweight, standards-aware export — not a full FHIR server. Maps
     * existing fields onto the FHIR resource shape; no coding/terminology
     * system involved.
     *
     * GET /api/prescriptions/{id}/fhir
     */
    public function exportFhir(int $prescriptionId): JsonResponse
    {
        $prescription = Prescription::with(['patient.user', 'doctor.user', 'medications.medication'])
            ->findOrFail($prescriptionId);

        // Role + ownership are enforced by the policy (see PrescriptionPolicy::view).
        $this->authorize('view', $prescription);

        $bundle = [
            'resourceType' => 'Bundle',
            'id' => (string) $prescription->id,
            'type' => 'collection',
            'timestamp' => $prescription->created_at->toIso8601String(),
            'entry' => $prescription->medications->map(function ($item) use ($prescription) {
                $medication = $item->medication;

                return [
                    'resource' => [
                        'resourceType' => 'MedicationRequest',
                        'id' => (string) $item->id,
                        'status' => 'active',
                        'intent' => 'order',
                        'medicationCodeableConcept' => ['text' => $medication?->name],
                        'subject' => [
                            'reference' => 'Patient/' . $prescription->patient_id,
                            'display' => $prescription->patient?->user?->full_name,
                        ],
                        'requester' => [
                            'reference' => 'Practitioner/' . $prescription->doctor_id,
                            'display' => $prescription->doctor?->user?->full_name,
                        ],
                        'dosageInstruction' => [
                            ['text' => trim(($medication?->dosage ?? '') . ' — ' . ($item->instructions ?? ''))],
                        ],
                        'dispenseRequest' => [
                            'quantity' => ['value' => $item->boxes, 'unit' => 'box'],
                        ],
                        'authoredOn' => $prescription->created_at->toIso8601String(),
                        'note' => $prescription->diagnosis
                            ? [['text' => 'Diagnosis: ' . $prescription->diagnosis]]
                            : [],
                    ],
                ];
            })->values()->all(),
        ];

        return response()->json($bundle);
    }
}
