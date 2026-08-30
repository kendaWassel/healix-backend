<?php

namespace Database\Seeders;

use App\Models\Medication;
use Illuminate\Database\Seeder;

/**
 * A real, fixed catalog of 50 common medications — not the old
 * Medication::factory()->count(20), which had no idempotency guard (grew by
 * 20 every rerun) and only ever picked from 5 fixed names via
 * fake()->randomElement(), so every run just added more duplicate rows.
 * firstOrCreate here keyed on name keeps this stable across reruns.
 */
class MedicationSeeder extends Seeder
{
    public function run(): void
    {
        $medications = [
            'Paracetamol' => '500mg', 'Ibuprofen' => '400mg', 'Amoxicillin' => '500mg',
            'Augmentin' => '625mg', 'Cephalexin' => '500mg', 'Azithromycin' => '250mg',
            'Metformin' => '500mg', 'Amlodipine' => '5mg', 'Atorvastatin' => '20mg',
            'Omeprazole' => '20mg', 'Cholecalciferol' => '1000IU', 'Vitamin C' => '500mg',
            'Aspirin' => '81mg', 'Warfarin' => '5mg', 'Losartan' => '50mg',
            'Metoprolol' => '50mg', 'Furosemide' => '40mg', 'Insulin Glargine' => '100IU/mL',
            'Salbutamol' => '100mcg', 'Montelukast' => '10mg', 'Cetirizine' => '10mg',
            'Loratadine' => '10mg', 'Prednisolone' => '5mg', 'Diclofenac' => '50mg',
            'Naproxen' => '250mg', 'Tramadol' => '50mg', 'Codeine' => '30mg',
            'Ciprofloxacin' => '500mg', 'Doxycycline' => '100mg', 'Metronidazole' => '400mg',
            'Fluconazole' => '150mg', 'Ranitidine' => '150mg', 'Domperidone' => '10mg',
            'Ondansetron' => '4mg', 'Loperamide' => '2mg', 'Oral Rehydration Salts' => '1 sachet',
            'Levothyroxine' => '50mcg', 'Sertraline' => '50mg', 'Fluoxetine' => '20mg',
            'Diazepam' => '5mg', 'Amitriptyline' => '25mg', 'Gabapentin' => '300mg',
            'Clopidogrel' => '75mg', 'Simvastatin' => '20mg', 'Hydrochlorothiazide' => '25mg',
            'Ferrous Sulfate' => '325mg', 'Folic Acid' => '5mg', 'Calcium Carbonate' => '500mg',
            'Multivitamin' => '1 tablet', 'Zinc Sulfate' => '20mg',
        ];

        foreach ($medications as $name => $dosage) {
            Medication::firstOrCreate(['name' => $name], ['dosage' => $dosage]);
        }
    }
}
