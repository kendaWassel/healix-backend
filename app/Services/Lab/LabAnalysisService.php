<?php

namespace App\Services\Lab;

use App\Exceptions\AI\AIServiceException;
use App\Exceptions\AI\AIServiceInvalidResponseException;
use App\Models\LabAnalysis;
use App\Models\MedicalRecord;
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
     * Exact map from the frontend's fixed PRE_EXISTING_CONDITIONS picker
     * (the `value` field — the only strings the medical record's dedicated
     * pre_existing_conditions column should ever contain) to the AI
     * service's own condition_name(s).
     *
     * An exact match against this known, closed vocabulary is used instead
     * of substring/fuzzy matching — several of these don't substring-match
     * their target at all (e.g. "Disease of liver" vs "Liver Disease
     * (Possible)": same words, different order).
     *
     * "Anemia" is generic (the picker has no anemia subtype), so it maps to
     * every anemia-type condition the service knows — if the service flags
     * any of them, it's reasonable to treat it as already known.
     *
     * "Hypertensive disorder" has no entry: the AI service's knowledge base
     * (knowledge/disease_patterns.py) has no hypertension pattern at all, so
     * there is nothing to map it to.
     *
     * @var array<string, array<int, string>>
     */
    protected const PICKER_CONDITION_MAP = [
        'Diabetes mellitus' => ['Diabetes Mellitus (Possible)'],
        'Chronic kidney disease' => ['Chronic Kidney Disease (Possible)'],
        'Anemia' => ['Anemia of Chronic Disease', 'Iron Deficiency Anemia', 'Folate Deficiency Anemia', 'Vitamin B12 Deficiency Anemia'],
        'Hypothyroidism' => ['Hypothyroidism'],
        'Hyperthyroidism' => ['Hyperthyroidism'],
        'Disease of liver' => ['Liver Disease (Possible)'],
        'Disorder of coronary artery' => ['Coronary Artery Disease Risk'],
        'Chronic heart failure' => ['Heart Failure Indicators'],
        'Cardiac arrhythmia' => ['Cardiac Arrhythmia Risk'],
        'Hyperlipidemia' => ['Hyperlipidemia (High Cholesterol)'],
    ];

    /**
     * Send a lab test file for AI analysis and persist the full result.
     *
     * Patient demographics default from the patient profile and the latest
     * medical record. Pre-existing conditions are always taken from the
     * patient's own medical record (never client-supplied) and limited to
     * the conditions the AI service actually recognizes.
     *
     * @throws AIServiceException
     */
    public function analyze(
        UploadedFile $file,
        Patient $patient,
        ?int $age = null,
        ?string $gender = null,
    ): LabAnalysis {
        $fields = $this->buildPatientFields($patient, $age, $gender);

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
            throw new AIServiceInvalidResponseException(__('ai.lab_no_report_id'));
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

        // Make the doctor-facing report visible in the patient's medical
        // record (best-effort — a missing PDF or record write never fails
        // the analysis itself).
        $this->attachToMedicalRecord($analysis, $patient);

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
     * Attach the doctor-facing report PDF to the patient's medical record
     * (creating one if none exists yet) so it shows up in the record's
     * attachments for the doctor to see, the same way manual uploads do.
     */
    protected function attachToMedicalRecord(LabAnalysis $analysis, Patient $patient): void
    {
        if (! $analysis->pdf_path) {
            return;
        }

        try {
            $record = $patient->medicalRecords()->latest('id')->first()
                ?? new MedicalRecord(['patient_id' => $patient->id]);

            if (! $record->exists) {
                $record->save();
            }

            Upload::create([
                'user_id' => $patient->user_id,
                'category' => 'lab_report',
                'file' => basename($analysis->pdf_path),
                'file_path' => $analysis->pdf_path,
                'mime' => 'application/pdf',
                'medical_record_id' => $record->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to attach lab report to medical record', [
                'lab_analysis_id' => $analysis->id,
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);
        }
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
     * @return array<string, string|int>
     */
    protected function buildPatientFields(Patient $patient, ?int $age, ?string $gender): array
    {
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

        $conditions = $this->recognizedPreExistingConditions($patient);

        if ($conditions !== '') {
            $fields['pre_existing_conditions'] = $conditions;
        }

        return $fields;
    }

    /**
     * The patient's pre-existing conditions (from their medical record's
     * dedicated pre_existing_conditions column — a JSON array of values
     * from the frontend's fixed picker, see MedicalRecord::$casts),
     * translated to the AI service's own condition_name(s) via
     * PICKER_CONDITION_MAP. Patients never choose these directly for an
     * analysis — they are always pulled from the saved record, and only
     * entries the service can recognize are sent, as its own canonical
     * English name(s) — which is what its condition matching expects back.
     *
     * Separate from chronic_diseases, which feeds the DDI condition-check
     * elsewhere and is not used here.
     */
    protected function recognizedPreExistingConditions(Patient $patient): string
    {
        // ->first() (not ->value()) so the model's array cast on
        // pre_existing_conditions applies instead of reading the raw JSON string.
        $record = $patient->medicalRecords()->latest('id')->first();

        $declared = array_filter(array_map('trim', (array) $record?->pre_existing_conditions));

        if (empty($declared)) {
            return '';
        }

        $pickerMapLower = array_change_key_case(self::PICKER_CONDITION_MAP, CASE_LOWER);

        $matched = [];

        foreach ($declared as $condition) {
            $conditionLower = mb_strtolower($condition);

            if (isset($pickerMapLower[$conditionLower])) {
                foreach ($pickerMapLower[$conditionLower] as $conditionName) {
                    $matched[$conditionName] = true;
                }
            }
        }

        return implode(', ', array_keys($matched));
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
