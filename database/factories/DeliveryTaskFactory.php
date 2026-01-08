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
            'delivery_id' => null,
            'status' => 'pending', // Default status
            'delivery_fee' => null,
            'assigned_at' => null,
            'picked_at' => null,
            'delivered_at' => null,
        ];
    }

    /**
     * Indicate that the delivery task is pending
     */
    public function pending()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
                'delivery_id' => null,
            ];
        });
    }

    /**
     * Indicate that the delivery agent is picking up the order
     */
    public function pickingUpTheOrder()
    {
        return $this->state(function (array $attributes) {
            $delivery = \App\Models\Delivery::inRandomOrder()->first() ?? \App\Models\Delivery::factory()->create();
            return [
                'status' => 'picking_up_the_order',
                'delivery_id' => $delivery->id,
                'delivery_fee' => fake()->randomFloat(2, 5000, 50000),
                'assigned_at' => now(),
            ];
        });
    }

    /**
     * Indicate that the delivery agent is on the way
     */
    public function onTheWay()
    {
        return $this->state(function (array $attributes) {
            $delivery = \App\Models\Delivery::inRandomOrder()->first() ?? \App\Models\Delivery::factory()->create();
            return [
                'status' => 'on_the_way',
                'delivery_id' => $delivery->id,
                'delivery_fee' => fake()->randomFloat(2, 5000, 50000),
                'assigned_at' => now()->subHours(1),
                'picked_at' => now()->subMinutes(30),
            ];
        });
    }

    /**
     * Indicate that the delivery task is delivered
     */
    public function delivered()
    {
        return $this->state(function (array $attributes) {
            $delivery = \App\Models\Delivery::inRandomOrder()->first() ?? \App\Models\Delivery::factory()->create();
            return [
                'status' => 'delivered',
                'delivery_id' => $delivery->id,
                'delivery_fee' => fake()->randomFloat(2, 5000, 50000),
                'assigned_at' => now()->subHours(2),
                'picked_at' => now()->subHours(1),
                'delivered_at' => now(),
            ];
        });
    }
}
