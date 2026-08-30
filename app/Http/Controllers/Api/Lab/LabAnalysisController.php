<?php

namespace App\Http\Controllers\Api\Lab;

use App\Exceptions\AI\AIServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\AnalyzeLabReportRequest;
use App\Http\Resources\LabAnalysisResource;
use App\Models\Consultation;
use App\Models\LabAnalysis;
use App\Models\Patient;
use App\Services\Lab\LabAnalysisService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LabAnalysisController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LabAnalysisService $labService
    ) {}

    /**
     * Upload a lab test file (CSV, Excel, PDF), run the AI analysis and
     * persist the full report for the authenticated patient.
     */
    public function analyze(AnalyzeLabReportRequest $request): JsonResponse
    {
        $patient = $this->resolvePatient($request);

        if (! $patient) {
            return $this->patientRequired();
        }

        return $this->handle('Lab report analyzed successfully.', function () use ($request, $patient) {
            $analysis = $this->labService->analyze(
                $request->file('file'),
                $patient,
                $request->filled('age') ? (int) $request->validated('age') : null,
                $request->validated('gender'),
            );

            return new LabAnalysisResource($analysis->load('upload'));
        });
    }

    /**
     * List the authenticated patient's lab analyses (newest first).
     */
    public function index(Request $request): JsonResponse
    {
        $patient = $this->resolvePatient($request);

        if (! $patient) {
            return $this->patientRequired();
        }

        $analyses = LabAnalysis::where('patient_id', $patient->id)
            ->with('upload')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('ai.lab_analyses_retrieved'),
            'data' => LabAnalysisResource::collection($analyses->items()),
            'meta' => [
                'current_page' => $analyses->currentPage(),
                'last_page' => $analyses->lastPage(),
                'per_page' => $analyses->perPage(),
                'total' => $analyses->total(),
            ],
        ]);
    }

    /**
     * Show one persisted lab analysis (full report).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $analysis = $this->findOwnedAnalysis($request, $id);

        if ($analysis instanceof JsonResponse) {
            return $analysis;
        }

        return response()->json([
            'success' => true,
            'message' => __('ai.lab_analysis_retrieved'),
            'data' => new LabAnalysisResource($analysis->load('upload')),
        ]);
    }

    /**
     * Download the detailed (doctor) PDF report.
     */
    public function downloadPdf(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        return $this->downloadReport($request, $id, patientVersion: false);
    }

    /**
     * Download the simplified patient PDF report.
     */
    public function downloadPatientPdf(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        return $this->downloadReport($request, $id, patientVersion: true);
    }

    /**
     * Doctor: list every patient who has had a consultation with them, each
     * with their full lab analyses (test_results + conditions) embedded.
     *
     * "My patients" is scoped via Consultation (doctor_id/patient_id) — the
     * established doctor-patient relationship in this app — not the open
     * any-patient access that view-details/indexForPatient use.
     */
    public function myPatientsLabAnalyses(Request $request): JsonResponse
    {
        $doctor = $request->user()->doctor;

        $patientIds = Consultation::where('doctor_id', $doctor->id)
            ->distinct()
            ->pluck('patient_id');

        $patients = Patient::whereIn('id', $patientIds)
            ->with(['user', 'labAnalyses' => fn ($query) => $query->latest()])
            ->paginate($request->integer('per_page', 15));

        $data = collect($patients->items())->map(fn (Patient $patient) => [
            'patient_id' => $patient->id,
            'patient_name' => $patient->user?->full_name,
            'gender' => $patient->gender,
            'lab_analyses' => LabAnalysisResource::collection($patient->labAnalyses),
        ])->values();

        return response()->json([
            'success' => true,
            'message' => __('ai.lab_doctor_patients_retrieved'),
            'data' => $data,
            'meta' => [
                'current_page' => $patients->currentPage(),
                'last_page' => $patients->lastPage(),
                'per_page' => $patients->perPage(),
                'total' => $patients->total(),
            ],
        ]);
    }

    /**
     * Doctor/Nurse/Physiotherapist: list a specific patient's lab analyses
     * (newest first). Same audience as MedicalRecordController::viewDetails.
     */
    public function indexForPatient(Request $request, int $patientId): JsonResponse
    {
        $patient = Patient::find($patientId);
        if (! $patient) {
            return $this->patientNotFound();
        }

        $this->authorize('view', $patient);

        $analyses = LabAnalysis::where('patient_id', $patientId)
            ->with('upload')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('ai.lab_analyses_retrieved'),
            'data' => LabAnalysisResource::collection($analyses->items()),
            'meta' => [
                'current_page' => $analyses->currentPage(),
                'last_page' => $analyses->lastPage(),
                'per_page' => $analyses->perPage(),
                'total' => $analyses->total(),
            ],
        ]);
    }

    /**
     * Doctor/Nurse/Physiotherapist: view one of a patient's lab analyses
     * in full detail.
     */
    public function showForPatient(int $patientId, int $id): JsonResponse
    {
        $analysis = $this->findAnalysisForPatient($patientId, $id);

        if ($analysis instanceof JsonResponse) {
            return $analysis;
        }

        return response()->json([
            'success' => true,
            'message' => __('ai.lab_analysis_retrieved'),
            'data' => new LabAnalysisResource($analysis->load('upload')),
        ]);
    }

    /**
     * Doctor/Nurse/Physiotherapist: download the detailed (doctor) PDF
     * report for one of a patient's lab analyses.
     */
    public function downloadPdfForPatient(int $patientId, int $id): BinaryFileResponse|JsonResponse
    {
        return $this->downloadReportForPatient($patientId, $id, patientVersion: false);
    }

    /**
     * Doctor/Nurse/Physiotherapist: download the simplified patient PDF
     * for one of a patient's lab analyses.
     */
    public function downloadPatientPdfForPatient(int $patientId, int $id): BinaryFileResponse|JsonResponse
    {
        return $this->downloadReportForPatient($patientId, $id, patientVersion: true);
    }

    /**
     * Health/status of the LabInsight AI microservice.
     */
    public function health(): JsonResponse
    {
        return $this->handle('Lab analysis service is reachable.', fn () => $this->labService->health());
    }

    /**
     * All lab test markers the AI service can interpret.
     */
    public function supportedTests(): JsonResponse
    {
        return $this->handle('Supported tests retrieved successfully.', fn () => $this->labService->supportedTests());
    }

    /**
     * The AI service's full reference-range knowledge base.
     */
    public function referenceRanges(): JsonResponse
    {
        return $this->handle('Reference ranges retrieved successfully.', fn () => $this->labService->referenceRanges());
    }
    
    protected function downloadReport(Request $request, int $id, bool $patientVersion): BinaryFileResponse|JsonResponse
    {
        $analysis = $this->findOwnedAnalysis($request, $id);

        if ($analysis instanceof JsonResponse) {
            return $analysis;
        }

        try {
            $path = $this->labService->pdfPath($analysis, $patientVersion);
        } catch (AIServiceException $e) {
            Log::error('Lab report PDF download failed', [
                'lab_analysis_id' => $analysis->id,
                'patient_version' => $patientVersion,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('ai.lab_pdf_unavailable'),
            ], 404);
        }

        $filename = $patientVersion
            ? "LabInsight_Patient_Summary_{$analysis->report_id}.pdf"
            : "LabInsight_Report_{$analysis->report_id}.pdf";

        return response()->download($path, $filename, ['Content-Type' => 'application/pdf']);
    }

    protected function downloadReportForPatient(int $patientId, int $id, bool $patientVersion): BinaryFileResponse|JsonResponse
    {
        $analysis = $this->findAnalysisForPatient($patientId, $id);

        if ($analysis instanceof JsonResponse) {
            return $analysis;
        }

        try {
            $path = $this->labService->pdfPath($analysis, $patientVersion);
        } catch (AIServiceException $e) {
            Log::error('Lab report PDF download failed', [
                'lab_analysis_id' => $analysis->id,
                'patient_version' => $patientVersion,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('ai.lab_pdf_unavailable'),
            ], 404);
        }

        $filename = $patientVersion
            ? "LabInsight_Patient_Summary_{$analysis->report_id}.pdf"
            : "LabInsight_Report_{$analysis->report_id}.pdf";

        return response()->download($path, $filename, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Load an analysis and make sure it belongs to the authenticated patient.
     */
    protected function findOwnedAnalysis(Request $request, int $id): LabAnalysis|JsonResponse
    {
        $patient = $this->resolvePatient($request);

        if (! $patient) {
            return $this->patientRequired();
        }

        $analysis = LabAnalysis::where('id', $id)
            ->where('patient_id', $patient->id)
            ->first();

        if (! $analysis) {
            return response()->json([
                'success' => false,
                'message' => __('ai.lab_analysis_not_found'),
            ], 404);
        }

        return $analysis;
    }

    /**
     * Load an analysis and make sure it belongs to the given patient
     * (doctor/care-provider access — not the authenticated user's own).
     */
    protected function findAnalysisForPatient(int $patientId, int $id): LabAnalysis|JsonResponse
    {
        $patient = Patient::find($patientId);
        if (! $patient) {
            return $this->patientNotFound();
        }

        $this->authorize('view', $patient);

        $analysis = LabAnalysis::where('id', $id)
            ->where('patient_id', $patientId)
            ->first();

        if (! $analysis) {
            return response()->json([
                'success' => false,
                'message' => __('ai.lab_analysis_not_found'),
            ], 404);
        }

        return $analysis;
    }

    protected function resolvePatient(Request $request): ?Patient
    {
        return $request->user()?->patient;
    }

    protected function patientRequired(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('ai.lab_patients_only'),
        ], 403);
    }

    protected function patientNotFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('messages.patient_not_found'),
        ], 404);
    }

    /**
     * Run a lab service call and wrap the outcome in the API's standard
     * success/message/data envelope.
     */
    protected function handle(string $successMessage, callable $callback): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'data' => $callback(),
            ]);
        } catch (AIServiceException $e) {
            Log::error('Lab analysis service error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $this->httpStatus($e->getCode()));
        } catch (\Throwable $e) {
            Log::error('Lab analysis unexpected error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('ai.lab_unexpected_error'),
            ], 500);
        }
    }

    protected function httpStatus(int $code): int
    {
        return $code >= 400 && $code < 600 ? $code : 502;
    }
}
