<?php

namespace Database\Factories;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Consultation>
 */
use App\Models\Consultation;

class ConsultationFactory extends Factory
{
    protected $model = Consultation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $callTypes = ['call_now', 'schedule'];
        $statuses = ['pending', 'in_progress', 'completed', 'cancelled'];

        $type = $this->faker->randomElement($callTypes);

        $scheduledAt = null;
        $status = $this->faker->randomElement($statuses);

        if ($type === 'schedule') {
            // scheduled within the next 7 days
            $scheduledAt = $this->faker->dateTimeBetween('now', '+7 days');
            // if scheduled, default status to 'scheduled' or leave random
            if (!in_array($status, ['in_progress', 'pending'])) {
                $status = 'pending';
            }
        } else {
            // call_now consultations are typically pending or in_progress
            if (!in_array($status, ['pending', 'in_progress', 'completed', 'cancelled'])) {
                $status = $this->faker->randomElement(['pending', 'in_progress']);
            }
        }

        return [
            'patient_id' => Patient::factory()->state(function () {
                return [
                    'user_id' => User::factory()->state(fn () => ['role' => 'patient']),
                ];
            }),
            'doctor_id' => Doctor::factory()->state(function () {
                return [
                    'user_id' => User::factory()->state(fn () => ['role' => 'doctor']),
                ];
            }),
            'type' => $type,
            'status' => 'pending', // Default status
            'scheduled_at' => $type === 'schedule' ? $this->faker->dateTimeBetween('now', '+7 days') : null,
        ];
    }

    /**
     * Indicate that the consultation is pending
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
     * Indicate that the consultation is in progress
     */
    public function inProgress()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'in_progress',
            ];
        });
    }

    /**
     * Indicate that the consultation is completed
     */
    public function completed()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'completed',
            ];
        });
    }

    /**
     * Indicate that the consultation is cancelled
     */
    public function cancelled()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'cancelled',
            ];
        });
    }

    /**
     * Indicate that the consultation is call_now type
     */
    public function callNow()
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'call_now',
                'scheduled_at' => null,
            ];
        });
    }

    /**
     * Indicate that the consultation is scheduled type
     */
    public function scheduled()
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'schedule',
                'scheduled_at' => $this->faker->dateTimeBetween('now', '+7 days'),
            ];
        });
    }
}