<?php

namespace Database\Factories;

use App\Models\Medication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories.Factory<\App\Models\Medication>
 */
class MedicationFactory extends Factory
{
    protected $model = Medication::class;

    public function definition(): array
    {
        return [
            'name'   => fake()->randomElement(['Panadol', 'Augmentin', 'Ibuprofen', 'Cough Syrup', 'Vitamin C']),
            'dosage' => fake()->randomElement(['250mg', '500mg', '625mg', '1g', '10ml']),
        ];
    }
}


