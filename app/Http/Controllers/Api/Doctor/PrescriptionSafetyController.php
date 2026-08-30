<?php

namespace App\Http\Controllers\Api\Doctor;

use App\Exceptions\AI\AIServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\VerifyDraftPrescriptionRequest;
use App\Models\Patient;
use App\Services\DDI\PrescriptionSafetyService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PrescriptionSafetyController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PrescriptionSafetyService $safetyService
    ) {}

    /**
     * Decision-support: verify a DRAFT prescription (patient + medication list)
     * BEFORE the doctor saves it. Reuses the exact same PrescriptionSafetyService
     * as the pharmacist flow — drug interactions (only when 2+ meds), allergy
     * conflicts, and pregnancy safety (only when the patient is pregnant).
     *
     * Read-only: creates no prescription, saves no medications, writes nothing.
     *
     * POST /api/doctor/prescriptions/verify
     */
    public function verify(VerifyDraftPrescriptionRequest $request): JsonResponse
    {
        // `exists:patients,id` already guarantees the patient; load the model so
        // the service can read its allergies / pregnancy status.
        $patient = Patient::find($request->validated('patient_id'));

        if (! $patient) {
            return response()->json([
                'success' => false,
                'message' => __('messages.patient_not_found'),
            ], 404);
        }

        // Outside the try/catch below on purpose: AuthorizationException
        // would otherwise be caught by `catch (\Throwable $e)` and turned
        // into a misleading 500 instead of reaching Laravel's 403 handler.
        $this->authorize('view', $patient);

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
            Log::error('Doctor draft prescription verification failed', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 502);
        } catch (\Throwable $e) {
            Log::error('Doctor draft prescription verification unexpected error', [
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
