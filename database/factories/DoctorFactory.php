<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Specialization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Doctor>
 */
class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition(): array
    {
        $startHour = fake()->numberBetween(1, int2: 11);
        $endHour   = fake()->numberBetween($startHour + 1, 12);

        // store as 24-hour DB-friendly TIME; accessors will format to 12-hour when read
        $from = Carbon::createFromTime($startHour, 0, 0)->format('H:i:s');
        $to   = Carbon::createFromTime($endHour + 12, 0, 0)->format('H:i:s');

        return [
            'user_id' => User::factory()->state(fn () => ['role' => 'doctor']),
            'specialization_id' => Specialization::inRandomOrder()->first()->id,
            'gender' => fake()->randomElement(['male', 'female']),
            'doctor_image_id' => null,
            'from' => $from,
            'to'   => $to,
            'certificate_file_id' => null,
            'consultation_fee' => fake()->numberBetween(100, 300),
            'rating_avg' => fake()->randomFloat(1, 3, 5),
        ];
    }
}


