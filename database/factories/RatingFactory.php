<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RatingFactory extends Factory
{
    protected $model = Rating::class;

    public function definition(): array
    {
        return [
            'consultation_id' => null,
            'order_id'        => null,
            'home_visit_id'   => null,
            'delivery_task_id' => null,
            'target_type'    => $this->faker->randomElement(['doctor', 'pharmacist', 'care_provider', 'delivery']),
            'stars'           => fake()->numberBetween(3, 5),
        ];
    }
}


