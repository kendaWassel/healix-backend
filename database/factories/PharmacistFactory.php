<?php

namespace Database\Factories;

use App\Models\Pharmacist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pharmacist>
 */
class PharmacistFactory extends Factory
{
    protected $model = Pharmacist::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(fn () => ['role' => 'pharmacist']),
            'pharmacy_name' => fake()->company() . ' Pharmacy',
            'cr_number' => fake()->unique()->numberBetween(100000, 999999),
            'license_file_id' => null,
            'address' => fake()->address(),
            'from' => fake()->time('08:00', '10:00'),
            'to' => fake()->time('18:00', '22:00'),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'bank_account' => (string) fake()->bankAccountNumber(),
            'rating_avg' => fake()->randomFloat(1, 3, 5),
        ];
    }
}

