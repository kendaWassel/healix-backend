<?php

namespace Database\Seeders;

use App\Models\Consultation;
use App\Models\User;
use Database\Seeders\Data\DemoScenarioData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ConsultationScenarioSeeder extends Seeder
{
    /**
     * 61 consultations — roughly a year of weekly follow-ups between the
     * one demo patient and doctor (48), one live in_progress call, ten
     * call-now requests still waiting to connect, and two upcoming
     * scheduled ones.
     *
     * Every date below is computed from DemoScenarioData::SEED_ANCHOR, not
     * now() — scheduled_at is part of each row's updateOrCreate match key,
     * so a now()-based date would silently stop matching (and start
     * duplicating instead of updating) the very next calendar day.
     */
    public function run(): void
    {
        $doctor = User::where('email', DemoScenarioData::DOCTOR_EMAIL)->first()?->doctor;
        $patient = User::where('email', DemoScenarioData::PATIENT_EMAIL)->first()?->patient;

        if (! $doctor || ! $patient) {
            $this->command->warn('ConsultationScenarioSeeder: run UserSeeder/PatientSeeder/DoctorSeeder first.');

            return;
        }

        $anchor = Carbon::parse(DemoScenarioData::SEED_ANCHOR);

        // Repeats every 9 rows: mostly completed (real history), with a
        // cancelled one mixed in — 'in_progress' is deliberately excluded
        // from this backward-dated bulk (a consultation "in progress" for
        // months makes no real-world sense); it gets one dedicated
        // present-dated row below instead.
        $statusCycle = [
            'completed', 'completed', 'completed', 'completed', 'completed',
            'completed', 'completed', 'cancelled', 'completed',
        ];
        $typeCycle = ['schedule', 'schedule', 'schedule', 'schedule', 'call_now'];

        $total = 48;
        for ($i = 0; $i < $total; $i++) {
            // Weekly cadence going back ~48 weeks from the anchor.
            $scheduledAt = $anchor->copy()->subWeeks($total - $i)->setHour(9 + ($i % 8))->setMinute(0)->setSecond(0);
            $status = $statusCycle[$i % count($statusCycle)];
            $type = $typeCycle[$i % count($typeCycle)];

            $data = [
                'type' => $type,
                'status' => $status,
                'payment_status' => $status === 'completed' ? 'paid' : 'pending',
            ];

            if ($status === 'completed') {
                $data['meeting_started_at'] = $scheduledAt;
            }

            Consultation::updateOrCreate(
                ['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => $type, 'scheduled_at' => $scheduledAt],
                $data
            );
        }

        // One happening right now.
        Consultation::updateOrCreate(
            ['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'call_now', 'scheduled_at' => null],
            ['status' => 'in_progress', 'payment_status' => 'paid', 'meeting_started_at' => $anchor->copy()->subMinutes(5)]
        );

        // Ten "call now" requests waiting to connect — patient has
        // requested an immediate call and it hasn't started yet.
        for ($i = 0; $i < 10; $i++) {
            $requestedAt = $anchor->copy()->subMinutes($i * 7 + 2);
            Consultation::updateOrCreate(
                ['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'call_now', 'scheduled_at' => $requestedAt],
                ['status' => 'pending', 'payment_status' => 'pending']
            );
        }

        // Two upcoming ones — "my scheduled consultations" shows something ahead too.
        foreach ([3, 10] as $daysAhead) {
            $scheduledAt = $anchor->copy()->addDays($daysAhead)->setHour(10)->setMinute(0)->setSecond(0);
            Consultation::updateOrCreate(
                ['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'schedule', 'scheduled_at' => $scheduledAt],
                ['status' => 'pending', 'payment_status' => 'pending']
            );
        }
    }
}
