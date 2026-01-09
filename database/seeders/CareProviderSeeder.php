<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\CareProvider;
use Illuminate\Database\Seeder;

class CareProviderSeeder extends Seeder
{

    public function run(): void
    {

        CareProvider::create([
            'user_id' => User::where('role', 'care_provider')->first()->id, 
            'type' => 'nurse', 
            'gender' => 'female', 
            'care_provider_image_id' => null, 
            'license_file_id' => null, 
            'session_fee' => 100,
        ]);

        CareProvider::create([
            'user_id' => 2,
            'type' => 'physiotherapist', 
            'gender' => 'male', 
            'care_provider_image_id' => null, 
            'license_file_id' => null, 
            'session_fee' => 100,
        ]);

        // Create 5 nurses
        CareProvider::factory()->count(5)->state([
            'type' => 'nurse',
        ])->create();

        // Create 5 physiotherapists
        CareProvider::factory()->count(5)->state([
            'type' => 'physiotherapist',
        ])->create();
    }
}

