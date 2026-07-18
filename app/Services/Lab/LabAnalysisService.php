<?php

namespace App\Services\Lab;

use App\Exceptions\AI\AIServiceException;
use App\Exceptions\AI\AIServiceInvalidResponseException;
use App\Models\LabAnalysis;
use App\Models\Patient;
use App\Models\Upload;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LabAnalysisService
{
    public function __construct(
        protected LabClient $client
    ) {}

    /**
     * @throws AIServiceException
     */
    public function health(): array
    {
        return $this->client->get('/api/health');
    }

    /**
     * @return array<int, string>
     *
     * @throws AIServiceException
     */
    public function supportedTests(): array
    {
        return $this->client->get('/api/supported-tests');
    }

    /**
     * @throws AIServiceException
     */
    public function referenceRanges(): array
    {
        return $this->client->get('/api/reference-ranges');
    }

    /**
     * Send a lab test file for AI analysis and persist the full result.
     *
     * Patient demographics default from the patient profile and the latest
     * medical record when not explicitly provided.
     *
     * @param  array<int, string>|null  $preExistingConditions
     *
     * @throws AIServiceException
     */
    public function analyze(
        UploadedFile $file,
        Patient $patient,
        ?int $age = null,
        ?string $gender = null,
        ?array $preExistingConditions = null,
    ): LabAnalysis {
        $fields = $this->buildPatientFields($patient, $age, $gender, $preExistingConditions);

        Log::info('Lab analysis started', [
            'patient_id' => $patient->id,
            'file_name' => $file->getClientOriginalName(),
            'fields' => $fields,
        ]);

        $report = $this->client->postMultipart(
            '/api/analyze',
            'file',
            $file->get(),
            $file->getClientOriginalName(),
            $fields,
        );

        $reportId = $report['report_id'] ?? null;

        if (! is_string($reportId) || $reportId === '') {
            throw new AIServiceInvalidResponseException('Lab analysis service did not return a report id.');
        }

        $upload = $this->storeOriginalFile($file, $patient);

        $analysis = LabAnalysis::create([
            'patient_id' => $patient->id,
            'upload_id' => $upload?->id,
            'report_id' => $reportId,
            'overall_severity' => $report['overall_severity'] ?? 'normal',
            'summary' => $report['summary'] ?? '',
            'total_tests_analyzed' => (int) ($report['total_tests_analyzed'] ?? 0),
            'abnormal_count' => (int) ($report['abnormal_count'] ?? 0),
            'normal_count' => (int) ($report['normal_count'] ?? 0),
            'patient_info' => $report['patient_info'] ?? null,
            'test_results' => $report['test_results'] ?? [],
            'conditions' => $report['conditions'] ?? [],
            'disclaimer' => $report['disclaimer'] ?? null,
            'analyzed_at' => $this->parseTimestamp($report['timestamp'] ?? null),
        ]);

        // The FastAPI service generates PDFs best-effort; copy them into
        // Laravel storage now, but never fail the analysis if they are missing.
        $this->fetchPdf($analysis, patientVersion: false, failSilently: true);
        $this->fetchPdf($analysis, patientVersion: true, failSilently: true);

        Log::info('Lab analysis persisted', [
            'lab_analysis_id' => $analysis->id,
            'report_id' => $reportId,
            'overall_severity' => $analysis->overall_severity,
            'abnormal_count' => $analysis->abnormal_count,
        ]);

        return $analysis->refresh();
    }

    /**
     * Absolute filesystem path of the stored PDF, fetching it from the
     * AI service on demand when it is not in Laravel storage yet.
     *
     * @throws AIServiceException
     */
    public function pdfPath(LabAnalysis $analysis, bool $patientVersion = false): string
    {
        $column = $patientVersion ? 'patient_pdf_path' : 'pdf_path';
        $stored = $analysis->{$column};

        if (! $stored || ! Storage::disk('public')->exists($stored)) {
            $stored = $this->fetchPdf($analysis, $patientVersion, failSilently: false);
        }

        return Storage::disk('public')->path($stored);
    }

    /**
     * Download a report PDF from the AI service into Laravel storage.
     *
     * Returns the relative storage path, or null when the download failed
     * and $failSilently is true.
     *
     * @throws AIServiceException
     */
    protected function fetchPdf(LabAnalysis $analysis, bool $patientVersion, bool $failSilently): ?string
    {
        $endpoint = $patientVersion
            ? "/api/reports/{$analysis->report_id}/patient-pdf"
            : "/api/reports/{$analysis->report_id}/pdf";

        $column = $patientVersion ? 'patient_pdf_path' : 'pdf_path';
        $suffix = $patientVersion ? '_patient' : '';
        $path = "lab_reports/pdf/{$analysis->report_id}{$suffix}.pdf";

        try {
            $bytes = $this->client->downloadBinary($endpoint);
        } catch (AIServiceException $e) {
            if ($failSilently) {
                Log::warning('Lab report PDF not available yet', [
                    'report_id' => $analysis->report_id,
                    'patient_version' => $patientVersion,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }

            throw $e;
        }

        Storage::disk('public')->put($path, $bytes);
        $analysis->forceFill([$column => $path])->save();

        return $path;
    }

    /**
     * Persist the original uploaded lab file so the source document stays
     * available even after the AI service is redeployed.
     */
    protected function storeOriginalFile(UploadedFile $file, Patient $patient): ?Upload
    {
        try {
            $path = $file->store('lab_reports/originals', 'public');

            return Upload::create([
                'user_id' => $patient->user_id,
                'category' => 'lab_report',
                'file' => basename($path),
                'file_path' => $path,
                'mime' => $file->getClientMimeType(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to store original lab file', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build the optional multipart form fields expected by POST /api/analyze:
     * age (int), gender (male|female|other), pre_existing_conditions
     * (comma-separated string).
     *
     * @param  array<int, string>|null  $preExistingConditions
     * @return array<string, string|int>
     */
    protected function buildPatientFields(
        Patient $patient,
        ?int $age,
        ?string $gender,
        ?array $preExistingConditions,
    ): array {
        $fields = [];

        $age ??= $patient->birth_date
            ? Carbon::parse($patient->birth_date)->age
            : null;

        if ($age !== null) {
            $fields['age'] = $age;
        }

        $gender = strtolower($gender ?? (string) $patient->gender);

        if (in_array($gender, ['male', 'female', 'other'], true)) {
            $fields['gender'] = $gender;
        }

        if ($preExistingConditions === null) {
            $chronic = $patient->medicalRecords()->latest()->value('chronic_diseases');

            if (is_string($chronic) && trim($chronic) !== '') {
                $preExistingConditions = [$chronic];
            }
        }

        if (! empty($preExistingConditions)) {
            $conditions = implode(', ', array_filter(array_map('trim', $preExistingConditions)));

            if ($conditions !== '') {
                $fields['pre_existing_conditions'] = $conditions;
            }
        }

        return $fields;
    }

    protected function parseTimestamp(mixed $timestamp): ?Carbon
    {
        if (! is_string($timestamp) || $timestamp === '') {
            return now();
        }

        try {
            return Carbon::parse($timestamp);
        } catch (\Throwable) {
            return now();
        }
    }
}
