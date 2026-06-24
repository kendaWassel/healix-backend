<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\HomeVisit;
use App\Models\CareProvider;
use App\Models\Consultation;
use Illuminate\Database\Seeder;

class HomeVisitTestSeeder extends Seeder
{
    public function run(): void
    {
        $doctors = Doctor::all();
        $nurses = CareProvider::where('type', 'nurse')->get();
        $physiotherapists = CareProvider::where('type', 'physiotherapist')->get();
        $patients = Patient::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        if ($doctors->isEmpty() || $nurses->isEmpty() || $physiotherapists->isEmpty() || $patients->isEmpty()) {
            $this->command->warn('Please run PatientSeeder, DoctorSeeder, and CareProviderSeeder first.');
            return;
        }

        /**
         * ============================================
         * NURSE REQUESTS
         * ============================================
         */

        // Pending nurse requests -> nearby orders (NO care_provider_id)
        HomeVisit::factory()->count(10)->state([
            'patient_id' => fn() => $patients->random()->id,
            'doctor_id' => fn() => $doctors->random()->id,
            'consultation_id' => fn() => Consultation::factory()->create()->id,
            'care_provider_id' => null,
            'service_type' => 'nurse',
            'reason' => fake()->randomElement([
                'Insulin injection + Blood pressure',
                'Wound dressing + Medication administration',
                'Blood glucose monitoring',
                'IV therapy setup',
                'Nursing Care',
            ]),
            'status' => 'pending',
            'started_at' => null,
            'ended_at' => null,
            'scheduled_at' => now()->addDays(rand(1, 7))->setHour(rand(8, 18))->setMinute(0),
        ])->create();

        // Accepted nurse schedules
        foreach ($nurses->take(3) as $nurse) {
            HomeVisit::factory()->count(2)->state([
                'patient_id' => fn() => $patients->random()->id,
                'doctor_id' => fn() => $doctors->random()->id,
                'consultation_id' => fn() => Consultation::factory()->create()->id,
                'care_provider_id' => $nurse->id,
                'service_type' => 'nurse',
                'reason' => fake()->randomElement([
                    'Insulin injection + Blood pressure',
                    'Wound dressing + Medication administration',
                    'Blood glucose monitoring',
                    'IV therapy setup',
                    'Nursing Care',
                ]),
                'status' => 'accepted',
                'started_at' => null,
                'ended_at' => null,
                'scheduled_at' => now()->addDays(rand(1, 5))->setHour(rand(8, 18))->setMinute(0),
            ])->create();
        }

        // In progress nurse sessions
        foreach ($nurses->take(2) as $nurse) {
            HomeVisit::factory()->count(1)->state([
                'patient_id' => fn() => $patients->random()->id,
                'doctor_id' => fn() => $doctors->random()->id,
                'consultation_id' => fn() => Consultation::factory()->create()->id,
                'care_provider_id' => $nurse->id,
                'service_type' => 'nurse',
                'reason' => 'Nursing Care',
                'status' => 'in_progress',
                'started_at' => now()->subMinutes(30),
                'ended_at' => null,
                'scheduled_at' => now()->subHour(),
            ])->create();
        }

        /**
         * ============================================
         * PHYSIOTHERAPIST REQUESTS
         * ============================================
         */

        // Pending physiotherapist requests -> nearby orders (NO care_provider_id)
        HomeVisit::factory()->count(10)->state([
            'patient_id' => fn() => $patients->random()->id,
            'doctor_id' => fn() => $doctors->random()->id,
            'consultation_id' => fn() => Consultation::factory()->create()->id,
            'care_provider_id' => null,
            'service_type' => 'physiotherapist',
            'reason' => fake()->randomElement([
                'Physiotherapy Session',
                'Rehabilitation therapy + Mobility exercises',
                'Pain management + Stretching exercises',
                'Post-surgery physical therapy',
                'Sports injury rehabilitation',
            ]),
            'status' => 'pending',
            'started_at' => null,
            'ended_at' => null,
            'scheduled_at' => now()->addDays(rand(1, 7))->setHour(rand(8, 18))->setMinute(0),
        ])->create();

        // Accepted physiotherapist schedules
        foreach ($physiotherapists->take(3) as $physio) {
            HomeVisit::factory()->count(2)->state([
                'patient_id' => fn() => $patients->random()->id,
                'doctor_id' => fn() => $doctors->random()->id,
                'consultation_id' => fn() => Consultation::factory()->create()->id,
                'care_provider_id' => $physio->id,
                'service_type' => 'physiotherapist',
                'reason' => fake()->randomElement([
                    'Physiotherapy Session',
                    'Rehabilitation therapy + Mobility exercises',
                    'Pain management + Stretching exercises',
                    'Post-surgery physical therapy',
                    'Sports injury rehabilitation',
                ]),
                'status' => 'accepted',
                'started_at' => null,
                'ended_at' => null,
                'scheduled_at' => now()->addDays(rand(1, 5))->setHour(rand(8, 18))->setMinute(0),
            ])->create();
        }

        // In progress physiotherapist sessions
        foreach ($physiotherapists->take(2) as $physio) {
            HomeVisit::factory()->count(1)->state([
                'patient_id' => fn() => $patients->random()->id,
                'doctor_id' => fn() => $doctors->random()->id,
                'consultation_id' => fn() => Consultation::factory()->create()->id,
                'care_provider_id' => $physio->id,
                'service_type' => 'physiotherapist',
                'reason' => 'Physiotherapy Session',
                'status' => 'in_progress',
                'started_at' => now()->subMinutes(20),
                'ended_at' => null,
                'scheduled_at' => now()->subHour(),
            ])->create();
        }
    }
}