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
        'consultation_id' => Consultation::factory(),
        'doctor_id' => Doctor::factory(),
        'patient_id' => Patient::factory(),
        'pharmacist_id' => Pharmacist::factory(),
        'diagnosis' => $this->faker->word,
        'notes' => $this->faker->sentence,
        'source' => 'doctor_written',
        'status' => 'created',
        'total_quantity' => $this->faker->numberBetween(1, 10),
        'total_price' => $this->faker->randomFloat(2, 10, 200),
    ];
    }
}


