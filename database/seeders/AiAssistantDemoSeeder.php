<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\Conversation;
use App\Models\DoctorSummary;
use App\Models\Message;
use App\Models\Specialization;
use App\Models\User;
use Database\Seeders\Data\DemoScenarioData;
use Illuminate\Database\Seeder;

/**
 * IMPORTANT — read before trusting this data as "AI output": the
 * Conversation/Message/Assessment/DoctorSummary rows below are seeded
 * directly (schema-accurate, real enum/status values, real specialization
 * matches) because they can only otherwise be produced by calling the live
 * Healix Python service, which was not running during this work. Nothing
 * here is genuine model output — see docs/demo/DEMO_SCENARIOS_AR.md for the
 * explicit disclosure.
 *
 * All three cases below belong to the one demo patient (a real patient
 * plausibly opens the AI assistant more than once) rather than three
 * separate throwaway accounts.
 *
 * Constraints this seeder respects, taken from the real
 * HealixConversationService code (not invented):
 *  - `Assessment.triage` is always null — the current Healix pipeline has
 *    no non-emergency severity source and never writes a real value here.
 *  - `Conversation.status` is left at its default 'active' — no code path
 *    in this app ever sets it to 'completed'.
 *  - `Assessment.recommended_specialty` is matched against
 *    `specializations.name_ar` (case-insensitive exact match, per
 *    SpecializationResolver) — English `name` is not what's matched.
 */
class AiAssistantDemoSeeder extends Seeder
{
    public function run(): void
    {
        $cardiology = Specialization::where('name_ar', 'أمراض القلب')->first();
        $generalMedicine = Specialization::where('name_ar', 'طب عام')->first();
        $cardiologistDoctor = User::where('email', DemoScenarioData::DOCTOR_EMAIL)->first()?->doctor;
        $patientEmail = DemoScenarioData::PATIENT_EMAIL;

        // Case 1 — routine case that reached a diagnosis and a
        // doctor-reviewed summary (the patient already has a real completed
        // consultation with this same cardiologist — see
        // ConsultationScenarioSeeder).
        $this->seedCase(
            patientEmail: $patientEmail,
            sessionSuffix: 'cardiac-followup',
            isCrisis: false,
            severity: 'moderate',
            redFlags: null,
            messages: [
                ['patient', 'بحس بألم بصدري من يومين وضيق نفس خفيف'],
                ['assistant', 'من قديش تماماً بدأ الألم؟ هل بينتشر للذراع أو الرقبة؟'],
                ['patient', 'من يومين، وأحياناً بحس فيه لذراعي الشمال'],
                ['assistant', 'شكراً على المعلومات. بناءً على الأعراض، بنصحك تراجع طبيب قلبية.'],
            ],
            assessment: [
                'status' => Assessment::STATUS_COMPLETED,
                'recommended_specialty' => 'أمراض القلب',
                'specialty_id' => $cardiology?->id,
                'specialty_code' => $cardiology?->code,
                'specialty_name_ar' => $cardiology?->name_ar,
                'possible_diseases' => ['Angina pectoris', 'Hypertensive disorder'],
                'extracted_symptoms' => ['chest pain', 'shortness of breath', 'left arm pain'],
                'emergency_detected' => false,
            ],
            doctorSummary: [
                'doctor_id' => $cardiologistDoctor?->id,
                'status' => DoctorSummary::STATUS_SENT,
                'summary' => 'مريض يشتكي من ألم صدري متقطع مع ضيق نفس خفيف منذ يومين، وامتداد للذراع الأيسر أحياناً. يُنصح بتقييم قلبي شامل (تخطيط قلب + إنزيمات القلب) لاستبعاد سبب قلبي.',
            ],
        );

        // Case 2 — ordinary, non-urgent case, not yet reviewed by a doctor.
        $this->seedCase(
            patientEmail: $patientEmail,
            sessionSuffix: 'cold-symptoms',
            isCrisis: false,
            severity: 'low',
            redFlags: null,
            messages: [
                ['patient', 'عندي رشح وصداع خفيف من يومين'],
                ['assistant', 'هل عندك حرارة أو سعال؟'],
                ['patient', 'لا، بس زكام وصداع بسيط'],
                ['assistant', 'الأعراض تبدو نزلة برد بسيطة. يُفضّل الراحة وشرب السوائل، وإذا استمرت الأعراض أكتر من أسبوع راجع طبيب عام.'],
            ],
            assessment: [
                'status' => Assessment::STATUS_COMPLETED,
                'recommended_specialty' => 'طب عام',
                'specialty_id' => $generalMedicine?->id,
                'specialty_code' => $generalMedicine?->code,
                'specialty_name_ar' => $generalMedicine?->name_ar,
                'possible_diseases' => ['Common cold'],
                'extracted_symptoms' => ['runny nose', 'mild headache'],
                'emergency_detected' => false,
            ],
            doctorSummary: [
                'doctor_id' => null,
                'status' => DoctorSummary::STATUS_DRAFT,
                'summary' => 'أعراض نزلة برد خفيفة (رشح وصداع بسيط) منذ يومين، بدون حرارة. لا حاجة عاجلة لمراجعة طبيب حالياً.',
            ],
        );

        // Case 3 — emergency/red-flag case, from an earlier visit. `triage`
        // still stays null (see class docblock) — only
        // `is_crisis`/`emergency_detected`/`red_flags` carry the urgency
        // signal in this pipeline.
        $this->seedCase(
            patientEmail: $patientEmail,
            sessionSuffix: 'chest-pain-emergency',
            isCrisis: true,
            severity: 'critical',
            redFlags: ['severe chest pain', 'difficulty breathing', 'radiating arm pain'],
            messages: [
                ['patient', 'فجأة صار عندي ألم شديد بالصدر وصعوبة كبيرة بالتنفس'],
                ['assistant', 'هاي أعراض تستدعي رعاية طبية فورية. الرجاء التوجه لأقرب طوارئ حالاً أو الاتصال بالإسعاف.'],
            ],
            assessment: [
                'status' => Assessment::STATUS_COMPLETED,
                'recommended_specialty' => 'أمراض القلب',
                'specialty_id' => $cardiology?->id,
                'specialty_code' => $cardiology?->code,
                'specialty_name_ar' => $cardiology?->name_ar,
                'possible_diseases' => ['Myocardial infarction', 'Angina pectoris'],
                'extracted_symptoms' => ['severe chest pain', 'shortness of breath', 'left arm pain'],
                'emergency_detected' => true,
                'emergency_type' => 'possible_cardiac_event',
                'risk_reason' => 'ألم صدري شديد مفاجئ مع صعوبة تنفس وامتداد للذراع — نمط أعراض ينذر بحالة قلبية طارئة.',
            ],
            doctorSummary: null,
        );
    }

    protected function seedCase(
        string $patientEmail,
        string $sessionSuffix,
        bool $isCrisis,
        string $severity,
        ?array $redFlags,
        array $messages,
        array $assessment,
        ?array $doctorSummary,
    ): void {
        $user = User::where('email', $patientEmail)->first();
        $patient = $user?->patient;
        if (! $patient) {
            $this->command->warn("AiAssistantDemoSeeder: patient {$patientEmail} not found — run UserSeeder/PatientSeeder first.");

            return;
        }

        $sessionId = 'demo-' . $sessionSuffix;

        $conversation = Conversation::updateOrCreate(
            ['session_id' => $sessionId],
            [
                'patient_id' => $patient->id,
                'status' => 'active',
                'title' => $messages[0][1] ?? 'Demo conversation',
                'started_at' => now()->subHours(3),
                'ended_at' => now()->subHours(2)->addMinutes(45),
                'is_crisis' => $isCrisis,
                'severity' => $severity,
                'red_flags' => $redFlags,
            ]
        );

        Message::where('conversation_id', $conversation->id)->delete();
        foreach ($messages as $turn => [$sender, $text]) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender === 'patient' ? $user->id : null,
                'sender' => $sender,
                'message_type' => 'text',
                'message' => $text,
                'turn_number' => $turn + 1,
            ]);
        }

        $assessmentRow = Assessment::updateOrCreate(
            ['conversation_id' => $conversation->id],
            $assessment + ['triage' => null]
        );

        if ($doctorSummary) {
            DoctorSummary::updateOrCreate(
                ['conversation_id' => $conversation->id],
                $doctorSummary + [
                    'assessment_id' => $assessmentRow->id,
                    'patient_id' => $patient->id,
                ]
            );
        }
    }
}
