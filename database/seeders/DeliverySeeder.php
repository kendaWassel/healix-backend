<?php

namespace Database\Seeders;

use App\Models\Delivery;
use App\Models\User;
use Illuminate\Database\Seeder;

class DeliverySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('role', 'delivery')->first();
        if ($user) {
            Delivery::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'delivery_image_id' => null,
                    'vehicle_type' => 'motorbike',
                    'plate_number' => 'DEV' . ($user->id + 1000),
                    'bank_account' => null,
                    'driving_license_id' => null,
                ]
            );
        }

        // Create additional delivery profiles via factory
        Delivery::factory()->count(5)->create();
    }
}


