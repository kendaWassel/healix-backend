<?php

namespace App\Http\Controllers\Api\Pharmacist;

use App\Exceptions\AI\AIServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacist\VerifyPrescriptionRequest;
use App\Models\Prescription;
use App\Services\DDI\PrescriptionSafetyService;
use App\Services\PrescriptionMedicationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PrescriptionSafetyController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PrescriptionMedicationService $medicationService,
        protected PrescriptionSafetyService $safetyService,
    ) {}

    /**
     * Verify a prescription's clinical safety (drug interactions, allergy
     * conflicts and pregnancy safety) before pricing. Supports both prescription
     * types automatically:
     *
     *  - Uploaded image (patient_uploaded): the pharmacist's manually-entered
     *    medications are saved into prescription_medications first.
     *  - Electronic (doctor_written): the doctor's medications already exist, so
     *    they are loaded straight from the database — no entry required.
     *
     * POST /api/pharmacist/prescriptions/{prescription_id}/verify
     */
    public function verify(VerifyPrescriptionRequest $request, int $prescriptionId): JsonResponse
    {
        $prescription = Prescription::with('patient')->find($prescriptionId);

        if (! $prescription) {
            return response()->json([
                'success' => false,
                'message' => 'Prescription not found.',
            ], 404);
        }

        // Role + ownership are enforced by the policy (see PrescriptionPolicy::verify).
        $this->authorize('verify', $prescription);

        try {
            // Uploaded image prescriptions: persist the pharmacist-entered
            // medications first. Electronic prescriptions already have them.
            if ($prescription->source === 'patient_uploaded') {
                $this->medicationService->syncEnteredMedications(
                    $prescription,
                    $request->validated('medications')
                );
            }

            // Verify using the prescription's medications (saved above for
            // uploads, or the doctor's original list for electronic ones).
            $prescription->load('medications.medication');
            $report = $this->safetyService->verify($prescription);

            return response()->json([
                'success' => true,
                'message' => 'Prescription safety verification completed.',
                'data' => $report,
            ]);
        } catch (AIServiceException $e) {
            Log::error('Prescription safety verification failed', [
                'prescription_id' => $prescription->id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 502);
        } catch (\Throwable $e) {
            Log::error('Prescription safety verification unexpected error', [
                'prescription_id' => $prescription->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during safety verification.',
            ], 500);
        }
    }
}
