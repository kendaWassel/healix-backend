<?php

namespace Database\Seeders;

use App\Models\Medication;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionMedication;
use App\Models\User;
use Database\Seeders\Data\DemoScenarioData;
use Illuminate\Database\Seeder;

class PharmacistVerificationSeeder extends Seeder
{
    /** Marker written into prescription notes so re-runs can find their own rows. */
    protected const MARKER = '[seed:pharmacist-verification]';

    /**
     * Prescriptions parked at "sent_to_pharmacy" for the demo pharmacist to
     * run Verify (safety check) against — kept against the ONE demo
     * patient's real profile (allergies: Penicillin/Aspirin, chronic:
     * Hypertensive disorder — see PatientSeeder) instead of the previous
     * fleet of eight synthetic allergy/pregnancy/condition-specific
     * patients. That fleet existed to exercise every DDI edge case in
     * isolation during development; a real demo reviewer clicking through
     * the pharmacist's "verify" screen just needs to see a real pass and a
     * real, correctly-triggered warning, which this still provides.
     */
    public function run(): void
    {
        $pharmacist = User::where('email', DemoScenarioData::PHARMACIST_EMAIL)->first()?->pharmacist;
        $doctor = User::where('email', DemoScenarioData::DOCTOR_EMAIL)->first()?->doctor;
        $patient = User::where('email', DemoScenarioData::PATIENT_EMAIL)->first()?->patient;

        if (! $pharmacist || ! $patient) {
            $this->command->error('PharmacistVerificationSeeder: run UserSeeder/PharmacistSeeder/PatientSeeder first.');

            return;
        }

        $blueprints = [
            // Real allergy hit — patient is allergic to Aspirin (PatientSeeder).
            ['meds' => ['Aspirin', 'Metformin'], 'label' => 'allergy-warning-aspirin'],

            // Real chronic-condition hit — NSAID against her Hypertensive
            // disorder (verify the live wording via GET /condition-check if
            // DrugCentral's condition strings ever change).
            ['meds' => ['Ibuprofen'], 'label' => 'condition-warning-hypertension'],

            // Clean — nothing in her profile flags this one.
            ['meds' => ['Paracetamol'], 'label' => 'clean-single-drug'],

            // --- DDI demo scenarios (docs/demo/DRUG_INTERACTION_DEMO_SCENARIOS_AR.md) ---
            // Every pairing here was checked live against the DDI service
            // (127.0.0.1:8002) before being written — see that doc for the
            // exact response each one produced.
            ['meds' => DemoScenarioData::SCENARIO_NO_INTERACTION, 'label' => 'ddi-scenario-no-interaction'],
            ['meds' => DemoScenarioData::SCENARIO_MAJOR_INTERACTION, 'label' => 'ddi-scenario-major-interaction'],
            ['meds' => DemoScenarioData::SCENARIO_MULTI_DRUG, 'label' => 'ddi-scenario-multi-drug'],
            ['meds' => DemoScenarioData::SCENARIO_UNKNOWN_DRUG, 'label' => 'ddi-scenario-unknown-drug'],
            ['meds' => DemoScenarioData::SCENARIO_BRAND_VS_GENERIC, 'label' => 'ddi-scenario-brand-vs-generic'],
        ];

        $created = 0;

        foreach ($blueprints as $index => $blueprint) {
            $label = self::MARKER . ' #' . ($index + 1) . ' ' . $blueprint['label'];

            $prescription = Prescription::updateOrCreate(
                ['notes' => $label],
                [
                    'patient_id' => $patient->id,
                    'pharmacist_id' => $pharmacist->id,
                    'doctor_id' => $doctor?->id,
                    'diagnosis' => 'Seeded verification test case',
                    'source' => 'doctor_written',
                    'status' => 'sent_to_pharmacy',
                    'total_price' => null,
                    'total_quantity' => null,
                ]
            );

            // Reset medications so re-running gives a predictable starting point.
            PrescriptionMedication::where('prescription_id', $prescription->id)->delete();

            foreach ($blueprint['meds'] as $name) {
                $medication = Medication::firstOrCreate(['name' => $name], ['dosage' => '']);

                PrescriptionMedication::create([
                    'prescription_id' => $prescription->id,
                    'medication_id' => $medication->id,
                    'boxes' => 1,
                ]);
            }

            $created++;
        }

        $this->command->info("Seeded {$created} prescriptions for pharmacist #{$pharmacist->id} ({$pharmacist->user?->email}).");
        $this->command->info('They are in status "sent_to_pharmacy" — list them via GET /api/pharmacist/prescriptions.');
    }
}
