<?php

namespace App\Http\Controllers\Api\Patient;

use App\Exceptions\AI\AIServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\VerifyDraftPrescriptionRequest;
use App\Services\DDI\PrescriptionSafetyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PrescriptionSafetyController extends Controller
{
    public function __construct(
        protected PrescriptionSafetyService $safetyService
    ) {}

    /**
     * Self-check: let a patient verify a DRAFT medication list (e.g. before an
     * appointment, or before buying something over the counter) against their
     * own allergies, pregnancy status and chronic conditions. Reuses the exact
     * same PrescriptionSafetyService as the doctor and pharmacist flows.
     *
     * Read-only: creates no prescription, saves no medications, writes nothing.
     * The patient is always the authenticated user — there is no patient_id
     * field, unlike the doctor's version of this endpoint.
     *
     * POST /api/patient/prescriptions/verify
     */
    public function verify(VerifyDraftPrescriptionRequest $request): JsonResponse
    {
        $patient = $request->user()->patient;

        if (! $patient) {
            return response()->json([
                'success' => false,
                'message' => __('messages.patient_not_found_for_user'),
            ], 404);
        }

        try {
            $report = $this->safetyService->verifyDraft(
                $patient,
                $request->validated('medications')
            );

            return response()->json([
                'success' => true,
                'message' => __('ai.verification_completed'),
                'data' => $report,
            ]);
        } catch (AIServiceException $e) {
            Log::error('Patient draft prescription verification failed', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 502);
        } catch (\Throwable $e) {
            Log::error('Patient draft prescription verification unexpected error', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('ai.verification_unexpected_error'),
            ], 500);
        }
    }
}
