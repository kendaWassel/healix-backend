<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Delivery;
use App\Models\Pharmacist;
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
            'phone' => '1234567890',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        User::create([
            'full_name' => 'Patient',
            'email' => 'patient@gmail.com',
            'phone' => '234567890',
            'password' => Hash::make('password'),
            'role' => 'patient',    
        ]);
        User::create([
            'full_name' => 'Doctor',
            'email' => 'doctor@gmail.com',
            'phone' => '3456789012',
            'password' => Hash::make('password'),
            'role' => 'doctor',
        ]); 
        User::create([
            'full_name' => 'Pharmacist',
            'email' => 'pharmacist@gmail.com',
            'phone' => '4567890123',
            'password' => Hash::make('password'),
            'role' => 'pharmacist',
        ]);
        User::create([
            'full_name' => 'Nurse',
            'email' => 'nurse@gmail.com',
            'phone' => '5678901234',
            'password' => Hash::make('password'),
            'role' => 'care_provider',
        ]);
        User::create([
            'full_name' => 'Physiotherapist',
            'email' => 'physiotherapist@gmail.com',
            'phone' => '6789012345',
            'password' => Hash::make('password'),
            'role' => 'care_provider',
        ]);
        User::create([
            'full_name' => 'Delivery',
            'email' => 'delivery@gmail.com',
            'phone' => '7890123456',
            'password' => Hash::make('password'),
            'role' => 'delivery',
        ]);

        
        
    }   
}
