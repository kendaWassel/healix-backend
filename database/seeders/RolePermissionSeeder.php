<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $doctor = Role::firstOrCreate(['name' => 'doctor']);
        $patient = Role::firstOrCreate(['name' => 'patient']);
        $careProvider = Role::firstOrCreate(['name' => 'care_provider']);
        $pharmacist = Role::firstOrCreate(['name' => 'pharmacist']);

        $startConsultation = Permission::firstOrCreate(['name' => 'start consultation']);
        $endConsultation = Permission::firstOrCreate(['name' => 'end consultation']);

        $uploadReports = Permission::firstOrCreate(['name' => 'upload reports']);
        $viewDashboard = Permission::firstOrCreate(['name' => 'view dashboard']);
        $followUpPatients = Permission::firstOrCreate(['name' => 'follow up patients']);
        $addPrice = Permission::firstOrCreate(['name' => 'add price']);

        $pharmacist->givePermissionTo([
            $addPrice,
        ]);

        $admin->givePermissionTo([
            $uploadReports,
            $viewDashboard,
        ]);
        $careProvider->givePermissionTo([
            $followUpPatients,
        ]);
        $doctor->givePermissionTo([
            $startConsultation,
            $endConsultation,
            $viewDashboard
        ]);
        $patient->givePermissionTo([
            $startConsultation,
            $endConsultation,
            $uploadReports,
            $viewDashboard
        ]);

    }
}
