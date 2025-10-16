<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // مستخدم دكتور
        User::create([
            'full_name' => 'Dr. Ahmad',
            'email' => 'doctor@test.com',
            'phone' => '1234567890',
            'role' => 'doctor',
            'password' => bcrypt('123456'),
        ]);

        // مستخدم مريض
        User::create([
            'full_name' => 'Patient Test',
            'email' => 'patient@test.com',
            'phone' => '0987654321',
            'role' => 'patient',
            'password' => bcrypt('123456'),
        ]);

        // مستخدم ادمن
        User::create([
            'full_name' => 'Admin Test',
            'email' => 'admin@test.com',
            'phone' => '111222333',
            'role' => 'admin',
            'password' => bcrypt('123456'),
        ]);

        // يمكنك إضافة باقي الأدوار بنفس الطريقة
    }
}
