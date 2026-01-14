<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(fn () => ['role' => 'delivery']),
            'delivery_image_id' => null,
            'vehicle_type' => fake()->randomElement(['motorcycle', 'car', 'van', 'truck']),
            'plate_number' => fake()->regexify('[A-Z]{2}[0-9]{4}'),
            'driving_license_id' => null,
            'rating_avg' => fake()->randomFloat(1, 3, 5),
        ];
    }
}

