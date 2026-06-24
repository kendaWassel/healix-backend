<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        $patientUsers = User::where('role', 'patient')->get();

        if ($patientUsers->isEmpty()) {
            return;
        }

        // اعمل patient profiles لليوزرات الموجودين
        foreach ($patientUsers as $index => $user) {
            Patient::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'birth_date' => now()->subYears(rand(20, 50))->toDateString(),
                    'gender' => $index % 2 === 0 ? 'male' : 'female',
                    'address' => 'Patient Address ' . ($index + 1),

                    // مهم للـ nearby
                    'latitude' => fake()->latitude(33, 34),
                    'longitude' => fake()->longitude(35, 37),
                ]
            );
        }

        // مرضى إضافيين تجريب
        Patient::factory()->count(5)->create();
    }
}