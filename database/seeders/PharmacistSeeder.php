<?php

namespace Database\Seeders;
use App\Models\User;
use App\Models\Pharmacist;
use Illuminate\Database\Seeder;

class PharmacistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('role', 'pharmacist')->first();
        if ($user) {
            Pharmacist::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'pharmacy_name' => 'Main Pharmacy',
                    'cr_number' => 100000 + $user->id,
                    'address' => '123 Pharmacy St',
                    'license_file_id' => null,
                    'from' => '09:00:00',
                    'to' => '18:00:00',
                    'latitude' => null,
                    'longitude' => null,
                    'bank_account' => null,
                ]
            );
        }

        // Create additional pharmacists via factory
        Pharmacist::factory()->count(5)->create();
    }
}


