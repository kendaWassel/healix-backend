<?php

namespace Database\Seeders;

use App\Models\Medication;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionMedication;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PharmacistVerificationSeeder extends Seeder
{
    /** Marker written into prescription notes so re-runs can find their own rows. */
    protected const MARKER = '[seed:pharmacist-verification]';

    /** Pharmacist that receives the prescriptions (falls back to the first one). */
    protected const PHARMACIST_EMAIL = 'pharmacist@gmail.com';

    public function run(): void
    {
        $pharmacist = User::where('email', self::PHARMACIST_EMAIL)->first()?->pharmacist
            ?? \App\Models\Pharmacist::first();

        if (! $pharmacist) {
            $this->command->error('No pharmacist found — run PharmacistSeeder first.');

            return;
        }

        $doctor = \App\Models\Doctor::first();

        $patients = $this->testPatients();

    
        $blueprints = [
           
            ['profile' => 'pregnant_allergic_panadol', 'source' => 'doctor_written',   'meds' => ['panadol', 'warfarin']],
            ['profile' => 'pregnant_allergic_panadol', 'source' => 'patient_uploaded', 'meds' => []],

            ['profile' => 'pregnant_allergic',   'source' => 'patient_uploaded', 'meds' => []],
            ['profile' => 'pregnant_allergic',   'source' => 'doctor_written',   'meds' => ['Warfarin', 'Aspirin']],
            ['profile' => 'pregnant_clean',      'source' => 'patient_uploaded', 'meds' => []],
            ['profile' => 'pregnant_clean',      'source' => 'doctor_written',   'meds' => ['Warfarin', 'Ibuprofen']],
            ['profile' => 'allergic_aspirin',    'source' => 'patient_uploaded', 'meds' => []],
           
            ['profile' => 'allergic_aspirin',    'source' => 'doctor_written',   'meds' => ['naproxen', 'Metformin']],
            ['profile' => 'allergic_multiple',   'source' => 'patient_uploaded', 'meds' => []],
            ['profile' => 'allergic_multiple',   'source' => 'doctor_written',   'meds' => ['Amoxicillin', 'Aspirin']],
            ['profile' => 'clean_male',          'source' => 'patient_uploaded', 'meds' => []],
            ['profile' => 'clean_male',          'source' => 'doctor_written',   'meds' => ['Paracetamol']],
        ];

        $created = 0;

        foreach ($blueprints as $index => $blueprint) {
            $patient = $patients[$blueprint['profile']];
            $label = self::MARKER . ' #' . ($index + 1) . ' ' . $blueprint['profile'];

            $prescription = Prescription::updateOrCreate(
                ['notes' => $label],
                [
                    'patient_id' => $patient->id,
                    'pharmacist_id' => $pharmacist->id,
                    'doctor_id' => $blueprint['source'] === 'doctor_written' ? $doctor?->id : null,
                    'diagnosis' => $blueprint['source'] === 'doctor_written' ? 'Seeded test case' : null,
                    'source' => $blueprint['source'],
                    'status' => 'sent_to_pharmacy',
                    'total_price' => null,
                    'total_quantity' => null,
                ]
            );

            // Reset medications so re-running gives a predictable starting point.
            PrescriptionMedication::where('prescription_id', $prescription->id)->delete();

            foreach ($blueprint['meds'] as $name) {
                $medication = Medication::firstOrCreate(['name' => $name], ['dosage' => '']);

                PrescriptionMedication::create([
                    'prescription_id' => $prescription->id,
                    'medication_id' => $medication->id,
                    'boxes' => 1,
                ]);
            }

            $created++;
        }

        $enriched = $this->enrichExistingPharmacistPatients($pharmacist);

        $this->command->info("Seeded {$created} prescriptions for pharmacist #{$pharmacist->id} ({$pharmacist->user?->email}).");
        $this->command->info('They are in status "sent_to_pharmacy" — list them via GET /api/pharmacist/prescriptions.');
        $this->command->info("Also gave allergy/pregnancy records to {$enriched} patient(s) behind his pre-existing prescriptions.");
    }

    protected function enrichExistingPharmacistPatients(\App\Models\Pharmacist $pharmacist): int
    {
        $seedPatientIds = Patient::whereHas('user', fn ($q) => $q->where('email', 'like', 'seed.%@healix.test'))
            ->pluck('id');

        $patientIds = Prescription::where('pharmacist_id', $pharmacist->id)
            ->whereNotIn('patient_id', $seedPatientIds)
            ->distinct()
            ->pluck('patient_id');

        // Rotated so the queue contains a mix of pregnant / allergic / clean.
        $profiles = [
            ['is_pregnant' => true,  'allergies' => 'panadol'],
            ['is_pregnant' => false, 'allergies' => 'Aspirin'],
            ['is_pregnant' => true,  'allergies' => null],
            ['is_pregnant' => false, 'allergies' => 'Aspirin, Amoxicillin'],
            ['is_pregnant' => false, 'allergies' => null],
        ];

        $count = 0;

        foreach ($patientIds as $index => $patientId) {
            $patient = Patient::find($patientId);
            if (! $patient) {
                continue;
            }

            $profile = $profiles[$index % count($profiles)];

            // Pregnancy is read from the LATEST record, so collapse to exactly
            // one record per patient to keep results deterministic.
            MedicalRecord::where('patient_id', $patient->id)->delete();
            MedicalRecord::create([
                'patient_id' => $patient->id,
                'doctor_id' => null,
                'allergies' => $profile['allergies'],
                // Pregnancy only makes sense for female patients.
                'is_pregnant' => $profile['is_pregnant'] && mb_strtolower((string) $patient->gender) === 'female',
            ]);

            $count++;
        }

        return $count;
    }

  
    protected function testPatients(): array
    {
        $profiles = [
            // Pregnant AND allergic to panadol — the profile behind the
            // showcase prescription (panadol + warfarin).
            'pregnant_allergic_panadol' => [
                'name' => 'Seed Patient Pregnant Allergic Panadol', 'gender' => 'female',
                'is_pregnant' => true, 'allergies' => 'panadol',
            ],
            'pregnant_allergic' => [
                'name' => 'Seed Patient Pregnant Allergic', 'gender' => 'female',
                'is_pregnant' => true, 'allergies' => 'Aspirin',
            ],
            'pregnant_clean' => [
                'name' => 'Seed Patient Pregnant Clean', 'gender' => 'female',
                'is_pregnant' => true, 'allergies' => null,
            ],
            'allergic_aspirin' => [
                'name' => 'Seed Patient Allergic Aspirin', 'gender' => 'female',
                'is_pregnant' => false, 'allergies' => 'Aspirin',
            ],
            'allergic_multiple' => [
                'name' => 'Seed Patient Allergic Multiple', 'gender' => 'male',
                'is_pregnant' => false, 'allergies' => 'Aspirin, Amoxicillin',
            ],
            'clean_male' => [
                'name' => 'Seed Patient Clean', 'gender' => 'male',
                'is_pregnant' => false, 'allergies' => null,
            ],
        ];

        $patients = [];

        foreach ($profiles as $key => $profile) {
            $user = User::updateOrCreate(
                ['email' => "seed.{$key}@healix.test"],
                [
                    'full_name' => $profile['name'],
                    'phone' => '0790' . str_pad((string) (crc32($key) % 1000000), 6, '0', STR_PAD_LEFT),
                    'role' => 'patient',
                    'password' => Hash::make('password'),
                    'status' => 'approved',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $patient = Patient::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'birth_date' => now()->subYears(30)->toDateString(),
                    'gender' => $profile['gender'],
                    'address' => 'Damascus',
                    'latitude' => 33.5138,
                    'longitude' => 36.2765,
                ]
            );

            MedicalRecord::where('patient_id', $patient->id)->delete();
            MedicalRecord::create([
                'patient_id' => $patient->id,
                'doctor_id' => null,
                'allergies' => $profile['allergies'],
                'is_pregnant' => $profile['is_pregnant'],
            ]);

            $patients[$key] = $patient;
        }

        return $patients;
    }
}
