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
            'status' => $status,
            'scheduled_at' => $scheduledAt,
        ];
    }

}