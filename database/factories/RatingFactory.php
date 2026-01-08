<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Rating;
use Illuminate\Database\Eloquent\Factories\Factory;

class RatingFactory extends Factory
{
    protected $model = Rating::class;

    public function definition(): array
    {
        return [
            'consultation_id' => null,
            'doctor_id'       => Doctor::inRandomOrder()->first()->id ?? Doctor::factory(),
            'patient_id'      => Patient::inRandomOrder()->first()->id ?? Patient::factory(),
            'order_id'        => null,
            'pharmacist_id'   => null,
            'stars'           => fake()->numberBetween(3, 5),
        ];
    }
}


