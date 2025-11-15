<?php

namespace Database\Factories;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Consultation>
 */
class ConsultationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $callTypes =  ['call_now', 'schedule'];
        $statuses = ['pending','scheduled','in_progress','completed','cancelled'];

        return [
            'patient_id' => User::factory()->state(fn () => [ 'role' => 'patient' ]),
            'doctor_id' => Doctor::factory(),
            'type' => fake()->randomElement($callTypes),
            'status' => fake()->randomElement($statuses),
            'scheduled_at' => Carbon::now()->addDays(fake()->numberBetween(1, 30))->setTime(
                fake()->numberBetween(9, 16), // hour between 9 AM and 4 PM
                fake()->randomElement([0, 15, 30, 45]) // quarter-hour increments
            ),
        ];
    }

}