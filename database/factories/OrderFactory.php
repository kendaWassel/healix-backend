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
            'patient_id'      => $prescription->patient_id ?? Patient::factory()->create()->id,
            'pharmacist_id'   => null,
            'status'          => 'pending', // Default status
            'rejection_reason'=> null,
            'total_amount'    => null,
        ];
    }

    /**
     * Indicate that the order is pending
     */
    public function pending()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
            ];
        });
    }

    /**
     * Indicate that the order has been sent to pharmacy
     */
    public function sentToPharmacy()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'sent_to_pharmacy',
            ];
        });
    }

    /**
     * Indicate that the order has been accepted by pharmacist
     */
    public function accepted()
    {
        return $this->state(function (array $attributes) {
            $prescription = Prescription::find($attributes['prescription_id']);
            $pharmacistId = $prescription?->pharmacist_id ?? (Pharmacist::inRandomOrder()->first() ?? Pharmacist::factory()->create())->id;
            
            return [
                'status' => 'accepted',
                'pharmacist_id' => $pharmacistId,
            ];
        });
    }

    /**
     * Indicate that the order has been rejected by pharmacist
     */
    public function rejected()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'rejected',
                'rejection_reason' => fake()->sentence(),
            ];
        });
    }

    /**
     * Indicate that the order is ready for delivery
     */
    public function readyForDelivery()
    {
        return $this->state(function (array $attributes) {
            $prescription = Prescription::find($attributes['prescription_id']);
            $pharmacistId = $prescription?->pharmacist_id ?? (Pharmacist::inRandomOrder()->first() ?? Pharmacist::factory()->create())->id;
            
            return [
                'status' => 'ready_for_delivery',
                'pharmacist_id' => $pharmacistId,
                'total_amount' => fake()->randomFloat(2, 10000, 2000000),
            ];
        });
    }

    /**
     * Indicate that the order is out for delivery
     */
    public function outForDelivery()
    {
        return $this->state(function (array $attributes) {
            $prescription = Prescription::find($attributes['prescription_id']);
            $pharmacistId = $prescription?->pharmacist_id ?? (Pharmacist::inRandomOrder()->first() ?? Pharmacist::factory()->create())->id;
            
            return [
                'status' => 'out_for_delivery',
                'pharmacist_id' => $pharmacistId,
                'total_amount' => fake()->randomFloat(2, 10000, 2000000),
            ];
        });
    }

    /**
     * Indicate that the order has been delivered
     */
    public function delivered()
    {
        return $this->state(function (array $attributes) {
            $prescription = Prescription::find($attributes['prescription_id']);
            $pharmacistId = $prescription?->pharmacist_id ?? (Pharmacist::inRandomOrder()->first() ?? Pharmacist::factory()->create())->id;
            
            return [
                'status' => 'delivered',
                'pharmacist_id' => $pharmacistId,
                'total_amount' => fake()->randomFloat(2, 10000, 2000000),
            ];
        });
    }
}


