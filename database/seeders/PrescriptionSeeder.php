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
            ->count(10)
            ->create()
            ->each(function (Prescription $prescription) {
                PrescriptionMedication::factory()
                    ->count(fake()->numberBetween(1, 4))
                    ->create([
                        'prescription_id' => $prescription->id,
                    ]);
            });
    }
}


