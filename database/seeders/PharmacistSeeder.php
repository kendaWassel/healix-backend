<?php

namespace Database\Seeders;

use App\Models\Pharmacist;
use App\Models\User;
use Database\Seeders\Data\DemoScenarioData;
use Illuminate\Database\Seeder;

class PharmacistSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', DemoScenarioData::PHARMACIST_EMAIL)->first();
        if (! $user) {
            return;
        }

        // updateOrCreate — this is the one fixed demo pharmacist; force its
        // profile back to these values on every rerun.
        Pharmacist::updateOrCreate(
            ['user_id' => $user->id],
            [
                'pharmacy_name' => 'Main Pharmacy',
                'cr_number' => 100000 + $user->id,
                'address' => '123 Pharmacy St, Damascus',
                'from' => '09:00:00',
                'to' => '18:00:00',
                'latitude' => 33.5138,
                'longitude' => 36.2765,
            ]
        );
    }
}
