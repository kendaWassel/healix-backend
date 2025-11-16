<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Doctor>
 */
class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(fn () => [ 'role' => 'doctor' ]),
            'specialization_id' => null, //cannot be null
            'gender' => fake()->randomElement(['male', 'female']),
            'doctor_image_id' => null,
            'from' => '09:00:00',
            'to' => '17:00:00',
            'certificate_file_id' => null,
            'consultation_fee' => fake()->numberBetween(100, 300),
            'bank_account' => (string) fake()->bankAccountNumber(),
            'rating_avg' => fake()->randomFloat(1, 3, 5),
        ];
    }
}


