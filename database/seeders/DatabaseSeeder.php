<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SpecializationsTableSeeder::class,
            UserSeeder::class,
            PatientSeeder::class,
            DoctorSeeder::class,
            PharmacistSeeder::class,
            CareProviderSeeder::class,
            DeliverySeeder::class,
            ConsultationSeeder::class,
            HomeVisitTestSeeder::class,
            MedicationSeeder::class,
            PrescriptionSeeder::class,
            OrderSeeder::class,
            UploadSeeder::class,
            DeliveryTaskSeeder::class,
            FaqSeeder::class,
            FirstAidSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}