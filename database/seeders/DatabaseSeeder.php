<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Delivery;
use App\Models\HomeVisit;
use App\Models\Pharmacist;
use App\Models\CareProvider;
use Illuminate\Database\Seeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\DoctorSeeder;
use Database\Seeders\PatientSeeder;
use Database\Seeders\DeliverySeeder;
use Database\Seeders\PharmacistSeeder;
use Database\Seeders\CareProviderSeeder;
use Database\Seeders\HomeVisitTestSeeder;

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
            MedicalRecordSeeder::class,
        ]);

    }
}
