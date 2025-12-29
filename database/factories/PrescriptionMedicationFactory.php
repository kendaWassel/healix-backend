<?php

namespace Database\Factories;

use App\Models\Medication;
use App\Models\Prescription;
use App\Models\PrescriptionMedication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories.Factory<\App\Models\PrescriptionMedication>
 */
class PrescriptionMedicationFactory extends Factory
{
    protected $model = PrescriptionMedication::class;

    public function definition(): array
    {
        return [
            'prescription_id' => Prescription::factory(),
            'medication_id'   => Medication::factory(),
            'boxes'           => (string) fake()->numberBetween(1, 5),
            'instructions'    => fake()->randomElement(['After meals', 'Before sleep', 'Twice daily', 'When needed']),
            'price'           => fake()->randomFloat(2, 5000, 50000),
        ];
    }
}


