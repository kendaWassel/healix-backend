<?php

namespace Database\Seeders;

use App\Models\Prescription;
use App\Models\PrescriptionMedication;
use Illuminate\Database\Seeder;

class PrescriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create prescriptions with medications
        Prescription::factory()
            ->count(20)
            ->create()
            ->each(function (Prescription $prescription) {
                PrescriptionMedication::factory()
                    ->count(fake()->numberBetween(1, 4))
                    ->create([
                        'prescription_id' => $prescription->id,
                    ]);
            });
        Prescription::factory()->count(20)->create();

        // Create a doctor-written prescription
        Prescription::factory()->count(20)->doctorWritten()->create();

        // Create a patient-uploaded prescription
        Prescription::factory()->count(20)->patientUploaded()->create();
    }

}


