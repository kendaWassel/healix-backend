<?php

namespace Database\Seeders;

use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\Data\DemoScenarioData;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', DemoScenarioData::PATIENT_EMAIL)->first();
        if (! $user) {
            return;
        }

        // updateOrCreate, not firstOrCreate — this is the one fixed demo
        // patient, so a rerun must force gender/address back to these
        // values, not silently leave whatever an earlier manual test set.
        // female — lets the same one account also demo sex-restricted RAG
        // diagnosis candidates and the pregnancy field on the medical record.
        $patient = Patient::updateOrCreate(
            ['user_id' => $user->id],
            [
                'birth_date' => now()->subYears(34)->toDateString(),
                'gender' => 'female',
                'address' => 'Damascus, Syria — Al-Mazzeh',
                'latitude' => 33.5024,
                'longitude' => 36.2565,
            ]
        );

        // One real, coherent clinical picture instead of an empty record —
        // a chronic condition, a couple of real DrugCentral-standard
        // allergies, and a current medication, so the medical-record screen,
        // the pharmacist's safety-verification checks, and the DDI
        // condition-check integration all have real data to work against.
        $record = MedicalRecord::firstOrNew(['patient_id' => $patient->id]);
        $record->fill([
            'allergies' => ['Penicillin', 'Aspirin'],
            'chronic_diseases' => ['Hypertensive disorder'],
            'current_medications' => ['Amlodipine 5mg'],
            'is_pregnant' => false,
        ]);
        $record->save();
    }
}
