<?php

namespace Database\Seeders;

use App\Models\CareProvider;
use App\Models\User;
use Database\Seeders\Data\DemoScenarioData;
use Illuminate\Database\Seeder;

class CareProviderSeeder extends Seeder
{
    public function run(): void
    {
        // updateOrCreate — these are the two fixed demo care providers;
        // force their profiles back to these values on every rerun (never
        // touches rating_avg, which RatingScenarioSeeder owns).
        $nurse = User::where('email', DemoScenarioData::NURSE_EMAIL)->first();
        if ($nurse) {
            CareProvider::updateOrCreate(
                ['user_id' => $nurse->id],
                [
                    'type' => 'nurse',
                    'gender' => 'female',
                    'session_fee' => 100,
                    'latitude' => 33.5138,
                    'longitude' => 36.2765,
                    'available' => true,
                ]
            );
        }

        $physio = User::where('email', DemoScenarioData::PHYSIOTHERAPIST_EMAIL)->first();
        if ($physio) {
            CareProvider::updateOrCreate(
                ['user_id' => $physio->id],
                [
                    'type' => 'physiotherapist',
                    'gender' => 'male',
                    'session_fee' => 120,
                    'latitude' => 33.5200,
                    'longitude' => 36.3000,
                    'available' => true,
                ]
            );
        }
    }
}
