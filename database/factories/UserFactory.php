<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone' => fake()->phoneNumber(),
            'role' => fake()->randomElement(['patient', 'doctor', 'pharmacist', 'care_provider', 'delivery']),
            'status' => 'approved',
            'is_active' => true,
            'approved_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'status' => 'pending',
            'is_active' => false,
            'approved_at' => null,
        ]);
    }

    public function patient(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'patient']);
    }

    public function doctor(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'doctor']);
    }
}
