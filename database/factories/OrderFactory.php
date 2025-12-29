<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Patient;
use App\Models\Pharmacist;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories.Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $prescription = Prescription::inRandomOrder()->first() ?? Prescription::factory()->create();

        return [
            'prescription_id' => $prescription->id,
            'patient_id'      => $prescription->patient_id ?? Patient::factory(),
            'pharmacist_id'   => Pharmacist::inRandomOrder()->first()->id ?? Pharmacist::factory(),
            'status'          => fake()->randomElement([
                'sent_to_pharmacy',
                'accepted',
                'rejected',
                'ready_for_delivery',
                'out_for_delivery',
                'delivered',
            ]),
            'rejection_reason'=> fake()->optional()->sentence(),
            'total_amount'    => fake()->randomFloat(2, 10000, 2000000),
        ];
    }
}


