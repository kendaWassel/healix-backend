<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Consultation;
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
            'diagnosis'             => fake()->randomElement(['Flu', 'Bacterial throat infection', 'Migraine', 'Allergy']),
            'notes'                 => fake()->sentence(),
            'source'                => 'doctor_written',
            'status'                => 'created',
            'prescription_image_id' => null,
        ];
    }
}


