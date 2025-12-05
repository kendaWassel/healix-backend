<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\CareProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'full_name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'admin'
        ]);
        User::create([
            'full_name' => 'Doctor',
            'email' => 'doctor@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'doctor'
        ]);
        User::create([
            'full_name' => 'Pharmacist',
            'email' => 'pharmacist@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'pharmacist'
        ]);
        User::create([
            'full_name' => 'Care Provider',
            'email' => 'careprovider@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'care_provider'
        ]);
        User::create([
            'full_name' => 'Delivery',
            'email' => 'delivery@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'delivery'
        ]);
        User::create([
            'full_name' => 'Patient',
            'email' => 'patient@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'patient'
        ]);
        CareProvider::create([
            'user_id' => User::where('role', 'care_provider')->first()->id,
            'care_provider_image_id' => null,
            'license_file_id' => null,
            'session_fee' => 100,
            'gender' => 'male',
            'type' => 'nurse',
        ]);
        CareProvider::create([
            'user_id' => User::where('role', 'care_provider')->first()->id,
            'care_provider_image_id' => null,
            'license_file_id' => null,
            'session_fee' => 150,
            'gender' =>'female',
            'type' => 'physiotherapist',
        ]);
    }
}
