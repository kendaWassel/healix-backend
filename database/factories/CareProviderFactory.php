<?php

namespace Database\Factories;

use App\Models\CareProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CareProvider>
 */
class CareProviderFactory extends Factory
{
    protected $model = CareProvider::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(fn () => [ 'role' => 'care_provider' ]),
            'care_provider_image_id' => null,
            'license_file_id' => null,
            'session_fee' => fake()->numberBetween(50, 200),
            'gender' => fake()->randomElement(['male', 'female']),
            'type' => fake()->randomElement(['nurse', 'physiotherapist']),
            'bank_account' => (string) fake()->bankAccountNumber(),
            'rating_avg' => fake()->randomFloat(1, 3, 5),
        ];
    }
}


