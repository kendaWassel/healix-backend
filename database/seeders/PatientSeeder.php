<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('role', 'patient')->first();
        if (!$user) {
            return;
        }

        Patient::firstOrCreate([
            'user_id' => $user->id,
        ],[
            'birth_date' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'address' => '123 Main St',
            'latitude' => null,
            'longitude' => null,
        ]);
        Patient::create([
            'user_id' => User::where('role', 'patient')->first()->id,
            'birth_date' => now()->subYears(30)->toDateString(),
            'gender' => 'female',
            'address' => '123 Main St',
            'latitude' => null,
            'longitude' => null,
        ]);
        Patient::factory()->count(5)->create();



    }
}
