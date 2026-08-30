<?php

namespace App\Services\Healix;

use App\Exceptions\AI\AIServiceException;
use App\Models\Patient;
use Carbon\Carbon;

class HealixAiService
{
    public function __construct(
        protected HealixAiClient $client
    ) {}

    /**
     * @throws AIServiceException
     */
    public function health(): array
    {
        return $this->client->health();
    }

    /**
     * One triage turn: derive patient_sex and medical_record_summary from
     * the patient's own profile + latest medical record (Laravel owns this
     * data — CLAUDE.md > Architecture on the Python side — the Python
     * service never infers either from conversation text), then forward to
     * the real /chat route.
     *
     * @throws AIServiceException
     */
    public function sendMessage(Patient $patient, string $threadId, string $message): array
    {
        return $this->client->sendMessage(
            $threadId,
            $message,
            $this->resolvePatientSex($patient),
            $this->buildMedicalRecordSummary($patient),
        );
    }

    /**
     * Passthrough to the separate, isolated health-education feature
     * (rag/health_education/ on the Python side) — used by
     * HealixConversationService::sendMessage() to classify a chat turn
     * before deciding whether to answer it directly or route it into the
     * real triage call above. Deliberately calls the client directly with
     * no patient-profile enrichment (unlike sendMessage() above): this
     * feature is stateless and generic by design (api/health_qa_contracts.py).
     *
     * @throws AIServiceException
     */
    public function askHealthQuestion(string $question, string $threadId): array
    {
        return $this->client->askHealthQuestion($question, $threadId);
    }

    /**
     * Maps Patient::gender to api/contracts.py's ChatRequest.patient_sex —
     * Literal["male", "female"] | None on the Python side, consumed by
     * nodes/rag_retrieve.py to gate sex-specific knowledge-base entries
     * (dysmenorrhea, PCOS, vaginal candidiasis). Safety-relevant: an
     * unconfirmed sex there triggers a clarifying follow-up question
     * rather than a guess, so null is the deliberately SAFE default here,
     * not data loss.
     *
     * The "what about other/null" question was checked against source, not
     * guessed:
     *   - database/migrations/2025_10_14_225532_create_patients_table.php:
     *     `$table->enum('gender', ['male', 'female'])->nullable();` — the
     *     patients table itself cannot store anything other than 'male',
     *     'female', or null. There is no reachable 'other' value for a
     *     real Patient row.
     *   - RegisterRequest's own patient-role validation requires
     *     'gender' => 'required|string|in:male,female' at registration, so
     *     new patients always have one of the two set — but the column
     *     stays nullable (legacy rows, or any patient created through a
     *     path that predates or bypasses that rule), so null is still a
     *     real, reachable case here, not just defensive padding.
     *   - LabAnalysisService::buildPatientFields() separately accepts
     *     'other' in its own in_array() check, but that is for an
     *     explicit $gender OVERRIDE parameter callers may pass manually —
     *     it is not evidence that Patient::gender itself can hold 'other'.
     *     Confirmed by the migration above, not inferred from that
     *     unrelated check.
     *
     * The match() below still falls through to null for anything outside
     * {'male', 'female'} — not because 'other' is reachable today, but so
     * this never silently coerces an unexpected value into a safety-
     * relevant field if the column's constraints ever change.
     */
    protected function resolvePatientSex(Patient $patient): ?string
    {
        return match ($patient->gender) {
            'male' => 'male',
            'female' => 'female',
            default => null,
        };
    }

    /**
     * Free-text summary for api.contracts.ChatRequest.medical_record_summary
     * — confirmed against that file directly before writing this: it is a
     * single `str | None` field ("a *filtered* summary... never a raw
     * record dump"), not LabAnalysisService::buildPatientFields()'s
     * structured {age, gender, pre_existing_conditions} shape, which
     * exists for the Lab service's own, different multipart contract. Age
     * is sourced the same way that method sources it (birth_date via
     * Carbon); chronic_diseases/allergies/current_medications come from
     * the latest medical record, same source, just prose instead of
     * separate fields.
     *
     * KNOWN GAP, flagged rather than silently built around: chronic_diseases
     * is stored as DrugCentral-standard ENGLISH condition names — confirmed
     * against database/migrations/2026_07_19_000001_convert_chronic_diseases_to_json_add_other_conditions.php's
     * own comment ("JSON array of DrugCentral-standard English names...
     * chosen from the app's condition picker"). The Python side's
     * chronic-condition-lowered red-flag rules (rules/red_flags.py) match
     * on ARABIC keywords only (e.g. frozenset({"سكري", "سكر"}) for
     * diabetes) — confirmed directly against that file. An English name
     * embedded here (e.g. "Type 2 Diabetes Mellitus") will NOT be
     * recognized by that specific mechanism, even though it is included in
     * the summary as general context. No translation layer exists on
     * either side to bridge this, and inventing one here — guessing at
     * DrugCentral-name-to-Arabic-keyword mappings — is exactly the kind of
     * unreviewed translation CLAUDE.md's own vocabulary discipline warns
     * against on the Python side. Left as-is, English names included
     * verbatim, until this gets an explicit decision (translate at build
     * time against a reviewed mapping, or accept that chronic-condition-
     * lowering only ever fires from what the patient says in the
     * conversation itself, not from this field).
     */
    protected function buildMedicalRecordSummary(Patient $patient): ?string
    {
        $lines = [];

        $age = $patient->birth_date ? Carbon::parse($patient->birth_date)->age : null;
        if ($age !== null) {
            $lines[] = "العمر: {$age} سنة";
        }

        $record = $patient->medicalRecords()->latest()->first();

        if ($record) {
            $chronic = $this->formatListField($record->chronic_diseases);
            if ($chronic !== null) {
                $lines[] = "أمراض مزمنة: {$chronic}";
            }

            $allergies = $this->formatListField($record->allergies);
            if ($allergies !== null) {
                $lines[] = "حساسية: {$allergies}";
            }

            $medications = $this->formatListField($record->current_medications);
            if ($medications !== null) {
                $lines[] = "أدوية حالية: {$medications}";
            }
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * @param  array<int, string>|null  $value
     */
    protected function formatListField(?array $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $items = array_values(array_filter(array_map('trim', $value), fn ($item) => $item !== ''));

        return $items === [] ? null : implode('، ', $items);
    }
}
