<?php

namespace App\Http\Controllers\Api\Lab;

use App\Exceptions\AI\AIServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\AnalyzeLabReportRequest;
use App\Http\Resources\LabAnalysisResource;
use App\Models\LabAnalysis;
use App\Models\Patient;
use App\Services\Lab\LabAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LabAnalysisController extends Controller
{
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
