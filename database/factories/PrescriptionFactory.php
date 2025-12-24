<?php

namespace Database\Factories;

use Phar;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Pharmacist;
use App\Models\Consultation;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;

    public function definition(): array
    {
        $consultation = Consultation::inRandomOrder()->first() ?? Consultation::factory()->create();

        return [
            'consultation_id'       => $consultation->id,
            'doctor_id'             => $consultation->doctor_id ?? Doctor::factory(),
            'patient_id'            => $consultation->patient_id ?? Patient::factory(),
            'pharmacist_id'          => Pharmacist::inRandomOrder()->first()?->id,
            'diagnosis'             => fake()->randomElement(['Flu', 'Bacterial throat infection', 'Migraine', 'Allergy']),
            'notes'                 => fake()->sentence(),
            'source'                => 'doctor_written',
            'status'                => 'created',
            'prescription_image_id' => null,
        ];
    }
}


