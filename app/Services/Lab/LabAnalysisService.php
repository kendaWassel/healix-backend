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
     * Arabic/English aliases for every condition the LabInsight AI service
     * recognizes (knowledge/disease_patterns.py), keyed by the exact
     * condition_name the service expects back in pre_existing_conditions.
     *
     * Patients record chronic diseases as free text in either language
     * (e.g. "سكري" or "diabetes"), which rarely matches the service's
     * English condition names verbatim — this table is the translation
     * layer between the two. Kept as a static list (not fetched from the
     * AI service) so this matching never depends on that service being up.
     *
     * @var array<string, array<int, string>>
     */
    protected const CONDITION_ALIASES = [
        'Allergic Reaction / Parasitic Infection (Possible)' => ['allergy', 'allergic reaction', 'parasite', 'parasitic infection', 'حساسية', 'تحسس', 'عدوى طفيلية', 'طفيليات'],
        'Anemia of Chronic Disease' => ['anemia', 'أنيميا', 'فقر دم', 'فقر الدم'],
        'Bacterial Infection (Possible)' => ['bacterial infection', 'infection', 'عدوى بكتيرية', 'التهاب بكتيري', 'ميكروب'],
        'Cardiac Arrhythmia Risk' => ['arrhythmia', 'irregular heartbeat', 'اضطراب نظم القلب', 'عدم انتظام ضربات القلب'],
        'Cholestasis (Bile Duct Obstruction)' => ['cholestasis', 'bile duct obstruction', 'ركود صفراوي', 'انسداد القناة الصفراوية'],
        'Chronic Inflammation' => ['chronic inflammation', 'inflammation', 'التهاب مزمن'],
        'Chronic Kidney Disease (Possible)' => ['kidney disease', 'ckd', 'chronic kidney disease', 'renal failure', 'فشل كلوي', 'مرض الكلى المزمن', 'قصور كلوي', 'الكلى'],
        'Coagulation Disorder (Possible)' => ['coagulation disorder', 'bleeding disorder', 'اضطراب تخثر الدم', 'مشكلة تخثر'],
        'Coronary Artery Disease Risk' => ['coronary artery disease', 'heart disease', 'مرض الشريان التاجي', 'مرض القلب التاجي', 'انسداد شرايين'],
        'Dehydration' => ['dehydration', 'جفاف'],
        'Diabetes Mellitus (Possible)' => ['diabetes', 'diabetic', 'diabetes mellitus', 'سكري', 'السكري', 'مرض السكر', 'داء السكري', 'السكر'],
        'Folate Deficiency Anemia' => ['folate deficiency', 'folic acid deficiency', 'نقص حمض الفوليك', 'نقص الفولات'],
        'Gout Risk (Hyperuricemia)' => ['gout', 'hyperuricemia', 'نقرس', 'ارتفاع حمض اليوريك'],
        'Heart Failure Indicators' => ['heart failure', 'قصور القلب', 'فشل القلب'],
        'Hyperkalemia (High Potassium)' => ['hyperkalemia', 'high potassium', 'فرط بوتاسيوم الدم', 'ارتفاع البوتاسيوم'],
        'Hyperlipidemia (High Cholesterol)' => ['hyperlipidemia', 'high cholesterol', 'cholesterol', 'ارتفاع الكوليسترول', 'فرط شحميات الدم', 'الكوليسترول'],
        'Hypernatremia (High Sodium)' => ['hypernatremia', 'high sodium', 'فرط صوديوم الدم', 'ارتفاع الصوديوم'],
        'Hyperthyroidism' => ['hyperthyroidism', 'overactive thyroid', 'فرط نشاط الغدة الدرقية', 'فرط الدرقية'],
        'Hypertriglyceridemia' => ['hypertriglyceridemia', 'high triglycerides', 'ارتفاع الدهون الثلاثية', 'فرط ثلاثي الغليسريد'],
        'Hypocalcemia (Low Calcium)' => ['hypocalcemia', 'low calcium', 'نقص كالسيوم الدم', 'انخفاض الكالسيوم'],
        'Hypoglycemia' => ['hypoglycemia', 'low blood sugar', 'هبوط سكر الدم', 'نقص سكر الدم'],
        'Hypokalemia (Low Potassium)' => ['hypokalemia', 'low potassium', 'نقص بوتاسيوم الدم', 'انخفاض البوتاسيوم'],
        'Hyponatremia (Low Sodium)' => ['hyponatremia', 'low sodium', 'نقص صوديوم الدم', 'انخفاض الصوديوم'],
        'Hypothyroidism' => ['hypothyroidism', 'underactive thyroid', 'قصور الغدة الدرقية', 'قصور الدرقية', 'كسل الغدة الدرقية'],
        'Iron Deficiency Anemia' => ['iron deficiency anemia', 'فقر دم بعوز الحديد', 'نقص الحديد'],
        'Leukopenia' => ['leukopenia', 'low white blood cells', 'نقص كريات الدم البيضاء', 'قلة الكريات البيض'],
        'Liver Disease (Possible)' => ['liver disease', 'hepatic disease', 'مرض الكبد', 'أمراض الكبد', 'التهاب الكبد'],
        'Polycythemia' => ['polycythemia', 'high red blood cells', 'كثرة الحمر', 'احمرار الدم'],
        'Thrombocytopenia' => ['thrombocytopenia', 'low platelets', 'نقص الصفائح الدموية', 'قلة الصفيحات'],
        'Thrombocytosis' => ['thrombocytosis', 'high platelets', 'زيادة الصفائح الدموية', 'كثرة الصفيحات'],
        'Viral Infection (Possible)' => ['viral infection', 'virus', 'عدوى فيروسية', 'التهاب فيروسي', 'فيروس'],
        'Vitamin B12 Deficiency Anemia' => ['vitamin b12 deficiency', 'b12 deficiency', 'نقص فيتامين ب12', 'نقص فيتامين b12'],
        'Vitamin D Deficiency' => ['vitamin d deficiency', 'نقص فيتامين د', 'نقص فيتامين d'],
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
     * The patient's chronic diseases (from their medical record), translated
     * to the AI service's own condition names via CONDITION_ALIASES.
     * Patients never choose these directly — they are always pulled from the
     * saved record, and only entries the service can recognize (in either
     * Arabic or English) are sent, as the service's own canonical English
     * name — which is what its condition matching expects back.
     */
    protected function recognizedPreExistingConditions(Patient $patient): string
    {
        $chronic = $patient->medicalRecords()->latest()->value('chronic_diseases');

        if (! is_string($chronic) || trim($chronic) === '') {
            return '';
        }

        $declared = array_filter(array_map('trim', explode(',', $chronic)));

        if (empty($declared)) {
            return '';
        }

        $matched = [];

        foreach ($declared as $disease) {
            $diseaseLower = mb_strtolower($disease);

            foreach (self::CONDITION_ALIASES as $conditionName => $aliases) {
                foreach ($aliases as $alias) {
                    $aliasLower = mb_strtolower($alias);

                    if (str_contains($diseaseLower, $aliasLower) || str_contains($aliasLower, $diseaseLower)) {
                        $matched[$conditionName] = true;
                        continue 2;
                    }
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
