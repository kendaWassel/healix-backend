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
     * Replace the prescription's medication list with the pharmacist-entered
     * names — the single source of truth for both prescription sources
     * (uploaded image or electronic). Any previously-saved medication not in
     * this list is removed, including a doctor's original electronic list.
     *
     * Idempotent: re-verifying with the same list never duplicates rows.
     * Prices/boxes are left untouched on kept rows — pricing is a separate step.
     *
     * @param  array<int, string>  $names
     * @return array<int, string> The distinct, saved medication names.
     */
    public function syncEnteredMedications(Prescription $prescription, array $names): array
    {
        $saved = [];

        DB::transaction(function () use ($prescription, $names, &$saved) {
            $keepMedicationIds = [];

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

                $keepMedicationIds[] = $medication->id;
                $saved[mb_strtolower($name)] = $name;
            }

            // Drop anything on the prescription that isn't in the confirmed
            // list — stale manual entries or the doctor's original meds.
            PrescriptionMedication::where('prescription_id', $prescription->id)
                ->whereNotIn('medication_id', $keepMedicationIds)
                ->delete();
        });

        return array_values($saved);
    }
}
