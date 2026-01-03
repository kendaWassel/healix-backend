<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryTask>
 */
class DeliveryTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => \App\Models\Order::factory(),
            'delivery_id' => \App\Models\Delivery::factory(),
            'status' => $this->faker->randomElement(['pending','picked_up_the_order','on_the_way','delivered']),
            'assigned_at' => $this->faker->optional()->dateTime(),
            'picked_at' => $this->faker->optional()->dateTime(),
            'delivered_at' => $this->faker->optional()->dateTime(),
        ];
    }
}
