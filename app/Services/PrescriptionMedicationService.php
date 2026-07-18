<?php

namespace App\Services;

use App\Models\Medication;
use App\Models\Prescription;
use App\Models\PrescriptionMedication;
use Illuminate\Support\Facades\DB;

/**
 * Persists pharmacist-entered medications onto a prescription.
 *
 * Single responsibility: writing rows into the existing prescription_medications
 * table (linked to the existing medications table). No pricing, no verification.
 */
class PrescriptionMedicationService
{
    /**
     * Save the manually-entered medication names against the prescription.
     *
     * Idempotent: each (prescription, medication) link is created at most once,
     * so re-verifying with the same list never duplicates rows. Prices/boxes are
     * left untouched — they are the responsibility of the pricing step.
     *
     * @param  array<int, string>  $names
     * @return array<int, string> The distinct, saved medication names.
     */
    public function syncEnteredMedications(Prescription $prescription, array $names): array
    {
        $saved = [];

        DB::transaction(function () use ($prescription, $names, &$saved) {
            foreach ($names as $name) {
                $name = trim($name);
                if ($name === '') {
                    continue;
                }

                // Reuse the existing medications table; dosage is unknown here
                // (names only), so create with an empty dosage if it's new.
                $medication = Medication::firstOrCreate(
                    ['name' => $name],
                    ['dosage' => '']
                );

                PrescriptionMedication::firstOrCreate([
                    'prescription_id' => $prescription->id,
                    'medication_id' => $medication->id,
                ]);

                $saved[mb_strtolower($name)] = $name;
            }
        });

        return array_values($saved);
    }
}
