<?php

namespace App\Services\AI;

use App\Models\User;
use Illuminate\Support\Facades\App;

/**
 * Orchestrates a single clinical-guidance turn.
 *
 * Responsibilities that STAY in Laravel (the isolation contract):
 *   - resolve the authenticated patient
 *   - build the medical profile from MySQL (Patient + latest MedicalRecord)
 *   - decide the response language (ar/en) and country
 *   - forward to the FastAPI service and normalize its reply
 *
 * The AI service never touches the DB; it only receives the profile fields
 * Laravel chooses to send.
 */
class ClinicalGuidanceService
{
    public function __construct(
        protected ClinicalGuidanceClient $client
    ) {}

    /**
     * Ask the clinical-guidance service one question.
     *
     * @param  array<int, array{role:string, content:string}>  $history  Prior turns (optional).
     * @return array{reply:string, risk_class:string, is_emergency:bool, provider:string, sources:array}
     */
    public function ask(User $user, string $message, array $history = [], ?string $language = null, ?string $countryCode = null): array
    {
        $language = $this->normalizeLanguage($language ?? App::getLocale());
        $countryCode = strtoupper($countryCode ?? 'SA');

        $messages = array_values(array_filter(array_map(function ($m) {
            $role = $m['role'] ?? 'user';
            $content = trim((string) ($m['content'] ?? ''));
            if ($content === '' || ! in_array($role, ['user', 'assistant'], true)) {
                return null;
            }
            return ['role' => $role, 'content' => $content];
        }, $history)));
        $messages[] = ['role' => 'user', 'content' => $message];

        $payload = [
            'messages' => $messages,
            'language' => $language,
            'countryCode' => $countryCode,
            'stream' => false,
            'patient' => $this->buildProfile($user),
        ];

        $data = $this->client->post('/api/chat', $payload);

        return [
            'reply' => (string) ($data['content'] ?? ''),
            'risk_class' => (string) ($data['risk_class'] ?? 'R0'),
            'is_emergency' => (bool) ($data['is_emergency'] ?? false),
            'provider' => (string) ($data['provider'] ?? 'unknown'),
            'sources' => is_array($data['sources'] ?? null) ? $data['sources'] : [],
            'diagnoses' => is_array($data['diagnoses'] ?? null) ? $data['diagnoses'] : [],
        ];
    }

    /** Only Arabic + English are supported; everything else → English. */
    protected function normalizeLanguage(?string $lang): string
    {
        $code = strtolower(substr((string) $lang, 0, 2));
        return in_array($code, ['ar', 'en'], true) ? $code : 'en';
    }

    /**
     * Build the PHI-scoped profile from MySQL. Age is derived from birth_date;
     * conditions / allergies / medications come from the latest medical record.
     */
    protected function buildProfile(User $user): array
    {
        $patient = $user->patient;
        if (! $patient) {
            return [];
        }

        $ageYears = null;
        if ($patient->birth_date) {
            $ageYears = $patient->birth_date->age ?? \Carbon\Carbon::parse($patient->birth_date)->age;
        }

        $record = $patient->medicalRecords()->latest()->first();

        $conditions = [];
        $allergies = [];
        $medications = [];
        $pregnant = null;

        if ($record) {
            $conditions = array_values(array_filter(array_merge(
                (array) ($record->chronic_diseases ?? []),
                $this->splitList($record->other_conditions ?? null),
            )));
            $allergies = $this->splitList($record->allergies ?? null);
            $medications = $this->splitList($record->current_medications ?? null);
            $pregnant = $record->is_pregnant ?? null;
        }

        return array_filter([
            'age_years' => $ageYears,
            'sex' => $patient->gender ? ($patient->gender === 'female' ? 'F' : 'M') : null,
            'pregnant' => $pregnant,
            'conditions' => $conditions,
            'allergies' => $allergies,
            'medications' => $medications,
        ], fn ($v) => $v !== null && $v !== []);
    }

    /** Normalize a comma/newline-separated free-text field into a clean list. */
    protected function splitList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', preg_split('/[,\n;]+/', $value))));
    }
}
