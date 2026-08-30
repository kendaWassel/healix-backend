<?php

namespace Database\Seeders;

use App\Models\CareProvider;
use App\Models\Consultation;
use App\Models\HomeVisit;
use App\Models\User;
use Database\Seeders\Data\DemoScenarioData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class HomeVisitTestSeeder extends Seeder
{
    /**
     * 50 home visits split evenly between the nurse and physiotherapist —
     * mostly completed history (spread back over the last year, weekly
     * cadence, anchored to DemoScenarioData::SEED_ANCHOR rather than
     * now() — scheduled_at is part of the history rows' updateOrCreate
     * match key, so a now()-based date would stop matching, and start
     * duplicating, the next calendar day) plus a handful of live
     * pending/accepted/in_progress rows (matched by reason/status instead,
     * so those can safely stay now()-relative).
     */
    public function run(): void
    {
        $doctor = User::where('email', DemoScenarioData::DOCTOR_EMAIL)->first()?->doctor;
        $patient = User::where('email', DemoScenarioData::PATIENT_EMAIL)->first()?->patient;
        $nurse = CareProvider::where('type', 'nurse')->first();
        $physio = CareProvider::where('type', 'physiotherapist')->first();

        // A completed consultation is what a real home-visit request is
        // raised against — reuse the real ones ConsultationScenarioSeeder
        // already created instead of Consultation::factory(), which would
        // silently create yet more phantom patient/doctor accounts via its
        // own nested factories.
        $consultation = Consultation::where('patient_id', $patient?->id)
            ->where('doctor_id', $doctor?->id)
            ->where('status', 'completed')
            ->first();

        if (! $doctor || ! $patient || ! $nurse || ! $physio || ! $consultation) {
            $this->command->warn('HomeVisitTestSeeder: run UserSeeder/PatientSeeder/DoctorSeeder/CareProviderSeeder/ConsultationScenarioSeeder first.');

            return;
        }

        $nurseReasons = [
            'Insulin injection + Blood pressure', 'Wound dressing + Medication administration',
            'Blood glucose monitoring', 'IV therapy setup', 'Post-op wound care', 'Nursing Care',
        ];
        $physioReasons = [
            'Physiotherapy Session', 'Rehabilitation therapy + Mobility exercises',
            'Pain management + Stretching exercises', 'Post-surgery physical therapy', 'Sports injury rehabilitation',
        ];

        $anchor = Carbon::parse(DemoScenarioData::SEED_ANCHOR);

        $historyCount = 23; // per provider -> 46 completed history rows total
        for ($i = 0; $i < $historyCount; $i++) {
            $scheduledAt = $anchor->copy()->subWeeks($historyCount - $i)->setHour(9 + ($i % 8))->setMinute(0)->setSecond(0);

            HomeVisit::updateOrCreate(
                ['patient_id' => $patient->id, 'service_type' => 'nurse', 'care_provider_id' => $nurse->id, 'scheduled_at' => $scheduledAt],
                [
                    'doctor_id' => $doctor->id,
                    'consultation_id' => $consultation->id,
                    'reason' => $nurseReasons[$i % count($nurseReasons)],
                    'status' => 'completed',
                    'started_at' => $scheduledAt,
                    'ended_at' => (clone $scheduledAt)->addHour(),
                ]
            );

            HomeVisit::updateOrCreate(
                ['patient_id' => $patient->id, 'service_type' => 'physiotherapist', 'care_provider_id' => $physio->id, 'scheduled_at' => $scheduledAt->copy()->addMinutes(1)],
                [
                    'doctor_id' => $doctor->id,
                    'consultation_id' => $consultation->id,
                    'reason' => $physioReasons[$i % count($physioReasons)],
                    'status' => 'completed',
                    'started_at' => $scheduledAt->copy()->addMinutes(1),
                    'ended_at' => (clone $scheduledAt)->addMinutes(1)->addMinutes(45),
                ]
            );
        }

        // A handful of current/live-state rows — pending (unassigned, so
        // it shows up in the nurse/physio "new requests" pool), accepted
        // (upcoming), and in_progress (happening right now).
        $liveRows = [
            ['type' => 'nurse', 'provider' => $nurse, 'reason' => 'Blood glucose monitoring', 'status' => 'pending', 'care_provider_id' => null, 'scheduled_at' => now()->addDays(2)->setHour(9)],
            ['type' => 'nurse', 'provider' => $nurse, 'reason' => 'IV therapy setup', 'status' => 'accepted', 'care_provider_id' => $nurse->id, 'scheduled_at' => now()->addDays(4)->setHour(11)],
            ['type' => 'nurse', 'provider' => $nurse, 'reason' => 'Nursing Care', 'status' => 'in_progress', 'care_provider_id' => $nurse->id, 'scheduled_at' => now()->subMinutes(30)],
            ['type' => 'physiotherapist', 'provider' => $physio, 'reason' => 'Rehabilitation therapy + Mobility exercises', 'status' => 'pending', 'care_provider_id' => null, 'scheduled_at' => now()->addDays(3)->setHour(10)],
            ['type' => 'physiotherapist', 'provider' => $physio, 'reason' => 'Post-surgery physical therapy', 'status' => 'accepted', 'care_provider_id' => $physio->id, 'scheduled_at' => now()->addDays(5)->setHour(14)],
            ['type' => 'physiotherapist', 'provider' => $physio, 'reason' => 'Sports injury rehabilitation', 'status' => 'in_progress', 'care_provider_id' => $physio->id, 'scheduled_at' => now()->subMinutes(20)],
        ];

        foreach ($liveRows as $row) {
            HomeVisit::updateOrCreate(
                ['patient_id' => $patient->id, 'service_type' => $row['type'], 'reason' => $row['reason'], 'status' => $row['status']],
                [
                    'doctor_id' => $doctor->id,
                    'consultation_id' => $consultation->id,
                    'care_provider_id' => $row['care_provider_id'],
                    'scheduled_at' => $row['scheduled_at'],
                    'started_at' => $row['status'] === 'in_progress' ? $row['scheduled_at'] : null,
                    'ended_at' => null,
                ]
            );
        }
    }
}
