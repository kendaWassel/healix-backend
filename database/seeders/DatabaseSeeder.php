<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with a full demo environment.
     * Order matters — each seeder assumes the ones before it already ran
     * (accounts before profiles, profiles before scenarios that reference
     * them). See docs/demo/DEMO_ACCOUNTS_AR.md and
     * docs/demo/DEMO_SCENARIOS_AR.md for what this produces.
     */
    public function run(): void
    {
        $this->call([
            // Reference data
            SpecializationsTableSeeder::class,

            // Accounts and role profiles
            UserSeeder::class,
            PatientSeeder::class,
            DoctorSeeder::class,
            PharmacistSeeder::class,
            CareProviderSeeder::class,
            DeliverySeeder::class,

            // Catalog
            MedicationSeeder::class,

            // Interconnected scenarios
            ConsultationScenarioSeeder::class,
            HomeVisitTestSeeder::class,
            PharmacyOrderScenarioSeeder::class,
            PharmacistVerificationSeeder::class,
            LabAnalysisScenarioSeeder::class,
            RatingScenarioSeeder::class,
            NotificationScenarioSeeder::class,
            AiAssistantDemoSeeder::class,

            // Filler / reference content
            UploadSeeder::class,
            FaqSeeder::class,
            FirstAidSeeder::class,
        ]);
    }
}
