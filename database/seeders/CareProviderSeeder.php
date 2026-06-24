<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\CareProvider;
use Illuminate\Database\Seeder;

class CareProviderSeeder extends Seeder
{
    public function run(): void
    {
        $careProviders = User::where('role', 'care_provider')->get();

        // Nurse
        if ($careProviders->count() > 0) {
            CareProvider::firstOrCreate(
                ['user_id' => $careProviders[0]->id],
                [
                    'type' => 'nurse',
                    'gender' => 'female',
                    'care_provider_image_id' => null,
                    'license_file_id' => null,
                    'session_fee' => 100,
                    'rating_avg' => 4.5,
                    'latitude' => 33.5138,
                    'longitude' => 36.2765,
                    'available' => true,
                ]
            );
        }

        // Physiotherapist
        if ($careProviders->count() > 1) {
            CareProvider::firstOrCreate(
                ['user_id' => $careProviders[1]->id],
                [
                    'type' => 'physiotherapist',
                    'gender' => 'male',
                    'care_provider_image_id' => null,
                    'license_file_id' => null,
                    'session_fee' => 120,
                    'rating_avg' => 4.2,
                    'latitude' => 33.5200,
                    'longitude' => 36.3000,
                    'available' => true,
                ]
            );
        }

        // 5 nurses
        CareProvider::factory()
            ->count(5)
            ->state([
                'type' => 'nurse',
                'available' => true,
            ])
            ->create();

        // 5 physiotherapists
        CareProvider::factory()
            ->count(5)
            ->state([
                'type' => 'physiotherapist',
                'available' => true,
            ])
            ->create();
    }
}