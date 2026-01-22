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
                ]
            );
        }
        Pharmacist::firstOrCreate(
            ['cr_number' => 1234567891],
            [
                'user_id' => User::where('role', 'pharmacist')->first()->id,
                'pharmacy_name' => 'Pharmacy',
                'address' => '123 Main St',
                'license_file_id' => null,
                'from' => '09:00:00',
                'to' => '17:00:00',
            ]
        );

        // Create additional pharmacists via factory
        Pharmacist::factory()->count(5)->create();

        // Create 24/7 Pharmacy
        $user247 = User::firstOrCreate(
            ['email' => '247pharmacy@example.com'],
            [
                'full_name' => '24/7 Pharmacy Service',
                'phone' => '+9876543210',
                'password' => bcrypt('password'),
                'role' => 'pharmacist',
                'status' => 'approved',
                'is_active' => true,
                'approved_at' => now(),
            ]
        );

        Pharmacist::firstOrCreate(
            ['user_id' => $user247->id],
            [
                'pharmacy_name' => '24/7 Emergency Pharmacy',
                'cr_number' => 999999999, // Reduced to fit integer
                'address' => 'Emergency Services Building, 24/7 Available',
                'license_file_id' => null,
                'from' => '00:00:00',
                'to' => '23:59:59',
                'latitude' => null,
                'longitude' => null,
            ]
        );
    }
}


