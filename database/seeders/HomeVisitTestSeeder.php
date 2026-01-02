<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\HomeVisit;
use App\Models\CareProvider;
use App\Models\Consultation;
use Illuminate\Database\Seeder;
use ParagonIE\Sodium\Core\Curve25519\H;

class HomeVisitTestSeeder extends Seeder
{
    public function run(): void
    {
        //create home visit
        HomeVisit::create([
            'patient_id' => 1,
            'doctor_id' => 1,
            'care_provider_id' => 1,
            'consultation_id' => 1,
            'scheduled_at' => now()->addDays(2)->setHour(10)->setMinute(0),
            'service_type' => 'nurse',
            'reason' => 'Insulin injection + Blood pressure',
            'status' => 'pending',
        ]);

        // Get existing doctors and care providers
        $doctors = Doctor::all();
        $nurses = CareProvider::where('type', 'nurse')->get();
        $physiotherapists = CareProvider::where('type', 'physiotherapist')->get();
        $patients = Patient::all();

        if ($doctors->isEmpty() || $nurses->isEmpty() || $physiotherapists->isEmpty() || $patients->isEmpty()) {
            $this->command->warn('Please run PatientSeeder, DoctorSeeder, and CareProviderSeeder first.');
            return;
        }


        // Create home visits for nurses - mix of pending and accepted 
        foreach ($nurses as $nurse) {
            // Create 3 pending orders
            HomeVisit::factory()->count(3)->state([
                'patient_id' => fn() => $patients->random()->id,
                'doctor_id' => fn() => $doctors->random()->id,
                'care_provider_id' => $nurse->id,
                'consultation_id' => fn() => Consultation::factory()->create()->id,
                'service_type' => 'nurse',
                'reason' => fake()->randomElement([
                    'Insulin injection + Blood pressure',
                    'Wound dressing + Medication administration',
                    'Blood glucose monitoring',
                    'IV therapy setup',
                    'Nursing Care'
                ]),
                'status' => 'pending',
            ])->create();

            // Create 2 accepted schedules 
            HomeVisit::factory()->count(2)->state([
                'patient_id' => fn() => $patients->random()->id,
                'doctor_id' => fn() => $doctors->random()->id,
                'care_provider_id' => $nurse->id,
                'consultation_id' => fn() => Consultation::factory()->create()->id,
                'service_type' => 'nurse',
                'reason' => fake()->randomElement([
                    'Insulin injection + Blood pressure',
                    'Wound dressing + Medication administration',
                    'Blood glucose monitoring',
                    'IV therapy setup',
                    'Nursing Care'
                ]),
                'status' => 'accepted',
            ])->create();
        }

        // Create home visits for physiotherapists - mix of pending and accepted for testing
        foreach ($physiotherapists as $physio) {
            // Create 3 pending orders 
            HomeVisit::factory()->count(3)->state([
                'patient_id' => fn() => $patients->random()->id,
                'doctor_id' => fn() => $doctors->random()->id,
                'care_provider_id' => $physio->id,
                'consultation_id' => fn() => Consultation::factory()->create()->id,
                'service_type' => 'physiotherapist',
                'reason' => fake()->randomElement([
                    'Physiotherapy Session',
                    'Rehabilitation therapy + Mobility exercises',
                    'Pain management + Stretching exercises',
                    'Post-surgery physical therapy',
                    'Sports injury rehabilitation'
                ]),
                'status' => 'pending',
            ])->create();

            // Create 2 accepted schedules 
            HomeVisit::factory()->count(2)->state([
                'patient_id' => fn() => $patients->random()->id,
                'doctor_id' => fn() => $doctors->random()->id,
                'care_provider_id' => $physio->id,
                'consultation_id' => fn() => Consultation::factory()->create()->id,
                'service_type' => 'physiotherapist',
                'reason' => fake()->randomElement([
                    'Physiotherapy Session',
                    'Rehabilitation therapy + Mobility exercises',
                    'Pain management + Stretching exercises',
                    'Post-surgery physical therapy',
                    'Sports injury rehabilitation'
                ]),
                'status' => 'accepted',
            ])->create();
        }
        HomeVisit::factory()->count(10)->create();
    }
}
