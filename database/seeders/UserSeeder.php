<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Data\DemoScenarioData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Exactly seven accounts — one per role, no extra "fleet" accounts.
     * Every other seeder in this pass hangs its scenario data off these
     * same seven instead of creating its own throwaway accounts.
     */
    public function run(): void
    {
        $accounts = [
            ['full_name' => 'Admin', 'email' => DemoScenarioData::ADMIN_EMAIL, 'phone' => '1234567890', 'role' => 'admin'],
            ['full_name' => 'Patient', 'email' => DemoScenarioData::PATIENT_EMAIL, 'phone' => '234567890', 'role' => 'patient'],
            ['full_name' => 'Doctor', 'email' => DemoScenarioData::DOCTOR_EMAIL, 'phone' => '3456789012', 'role' => 'doctor'],
            ['full_name' => 'Pharmacist', 'email' => DemoScenarioData::PHARMACIST_EMAIL, 'phone' => '4567890123', 'role' => 'pharmacist'],
            ['full_name' => 'Nurse', 'email' => DemoScenarioData::NURSE_EMAIL, 'phone' => '5678901234', 'role' => 'care_provider'],
            ['full_name' => 'Physiotherapist', 'email' => DemoScenarioData::PHYSIOTHERAPIST_EMAIL, 'phone' => '6789012345', 'role' => 'care_provider'],
            ['full_name' => 'Delivery', 'email' => DemoScenarioData::DELIVERY_EMAIL, 'phone' => '7890123456', 'role' => 'delivery'],
        ];

        foreach ($accounts as $row) {
            $this->createApproved($row['full_name'], $row['email'], $row['phone'], $row['role']);
        }

        // One doctor per remaining specialization (DOCTOR_EMAIL/Cardiology
        // above already covers one) — so every specialty is bookable.
        // Phone is derived from the email (crc32), not an incrementing
        // counter — a counter reassigns different numbers to different
        // accounts whenever this list's order or length changes, colliding
        // with numbers already claimed by existing rows.
        foreach (DemoScenarioData::SPECIALIST_DOCTORS as $email => $specializationName) {
            $phone = '39' . str_pad((string) (crc32($email) % 100000000), 8, '0', STR_PAD_LEFT);
            $this->createApproved("Dr. {$specializationName}", $email, $phone, 'doctor');
        }
    }

    protected function createApproved(string $fullName, string $email, string $phone, string $role): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'full_name' => $fullName,
                'phone' => $phone,
                'password' => Hash::make(DemoScenarioData::DEMO_PASSWORD),
                'role' => $role,
                'status' => 'approved',
                'is_active' => true,
                'approved_at' => now(),
                'email_verified_at' => now(),
            ]
        );
    }
}
