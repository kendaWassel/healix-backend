<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::create([
            'full_name' => 'Admin',
            'email' => 'admin@gmail.com',
            'phone' => '1234567890',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'approved',
            'is_active' => true,
            'approved_at' => now(),
            'admin_note' => 'Initial admin',
            'email_verified_at' => now(),
        ]);

        // Patient
        User::create([
            'full_name' => 'Patient',
            'email' => 'patient@gmail.com',
            'phone' => '234567890',
            'password' => Hash::make('password'),
            'role' => 'patient',
            'status' => 'approved',
            'is_active' => true,
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]);

        // Doctor
        User::create([
            'full_name' => 'Doctor',
            'email' => 'doctor@gmail.com',
            'phone' => '3456789012',
            'password' => Hash::make('password'),
            'role' => 'doctor',
            'status' => 'approved',
            'is_active' => true,
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]);

        // Pharmacist
        User::create([
            'full_name' => 'Pharmacist',
            'email' => 'pharmacist@gmail.com',
            'phone' => '4567890123',
            'password' => Hash::make('password'),
            'role' => 'pharmacist',
            'status' => 'approved',
            'is_active' => true,
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]);

        // Nurse
        User::create([
            'full_name' => 'Nurse',
            'email' => 'nurse@gmail.com',
            'phone' => '5678901234',
            'password' => Hash::make('password'),
            'role' => 'care_provider',
            'status' => 'approved',
            'is_active' => true,
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]);

        // Physiotherapist
        User::create([
            'full_name' => 'Physiotherapist',
            'email' => 'physiotherapist@gmail.com',
            'phone' => '6789012345',
            'password' => Hash::make('password'),
            'role' => 'care_provider',
            'status' => 'approved',
            'is_active' => true,
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]);

        // Delivery
        User::create([
            'full_name' => 'Delivery',
            'email' => 'delivery@gmail.com',
            'phone' => '7890123456',
            'password' => Hash::make('password'),
            'role' => 'delivery',
            'status' => 'approved',
            'is_active' => true,
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]);
    }
}
