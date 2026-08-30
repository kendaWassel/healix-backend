<?php

namespace Database\Seeders;

use App\Models\Delivery;
use App\Models\User;
use Database\Seeders\Data\DemoScenarioData;
use Illuminate\Database\Seeder;

class DeliverySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', DemoScenarioData::DELIVERY_EMAIL)->first();
        if (! $user) {
            return;
        }

        // updateOrCreate — this is the one fixed demo delivery agent; force
        // its profile back to these values on every rerun.
        Delivery::updateOrCreate(
            ['user_id' => $user->id],
            [
                'gender' => 'male',
                'vehicle_type' => 'motorcycle',
                'plate_number' => 'DEV' . (1000 + $user->id),
                'current_latitude' => 33.51,
                'current_longitude' => 36.29,
            ]
        );
    }
}
