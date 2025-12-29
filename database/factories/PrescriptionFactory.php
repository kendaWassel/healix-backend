<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Upload;
use App\Models\Patient;
use App\Models\Pharmacist;
use App\Models\Consultation;
use App\Models\Order;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;

    public function definition(): array
    {
        $source = fake()->randomElement(['doctor_written', 'patient_uploaded']);

        // For doctor_written, we need a consultation
        if ($source === 'doctor_written') {
            $consultation = Consultation::inRandomOrder()->first() ?? Consultation::factory()->create();
            $doctorId = $consultation->doctor_id;
            $patientId = $consultation->patient_id;
            $consultationId = $consultation->id;
            $prescriptionImageId = null;
        } else {
            // For patient_uploaded, no consultation
            $consultationId = null;
            $doctorId = null;
            $patient = Patient::inRandomOrder()->first() ?? Patient::factory()->create();
            $patientId = $patient->id;
            // Get a random prescription upload or null
            $prescriptionImageId = fake()->optional(0.7)->randomElement(
                Upload::where('category', 'prescription')->pluck('id')->toArray()
            );
        }

        return [
            'consultation_id' => $consultationId,
            'doctor_id' => $doctorId,
            'patient_id' => $patientId,
            'pharmacist_id' => fake()->optional(0.3)->randomElement(
                Pharmacist::pluck('id')->toArray()
            ),
            'diagnosis' => fake()->randomElement(['Flu', 'Bacterial throat infection', 'Migraine', 'Allergy']),
            'notes' => fake()->optional()->sentence(),
            'source' => $source,
            'status' => 'created',
            'total_quantity' => null, // Will be set when priced
            'total_price' => null, // Will be set when priced
            'prescription_image_id' => $prescriptionImageId,
        ];
    }

    /**
     * Indicate that the prescription is from a doctor
     */
    public function doctorWritten()
    {
        return $this->state(function (array $attributes) {
            $consultation = Consultation::inRandomOrder()->first() ?? Consultation::factory()->create();
            return [
                'source' => 'doctor_written',
                'consultation_id' => $consultation->id,
                'doctor_id' => $consultation->doctor_id,
                'patient_id' => $consultation->patient_id,
                'prescription_image_id' => null,
            ];
        });
    }

    /**
     * Indicate that the prescription is uploaded by patient
     */
    public function patientUploaded()
    {
        return $this->state(function (array $attributes) {
            $patient = Patient::inRandomOrder()->first() ?? Patient::factory()->create();
            return [
                'source' => 'patient_uploaded',
                'consultation_id' => null,
                'doctor_id' => null,
                'patient_id' => $patient->id,
                'prescription_image_id' => fake()->optional(0.7)->randomElement(
                    Upload::where('category', 'prescription')->pluck('id')->toArray()
                ),
            ];
        });
    }


    /**
     * Indicate that the prescription has been sent to pharmacy
     */
    public function sentToPharmacy()
    {
        return $this->state(function (array $attributes) {
            $pharmacist = Pharmacist::inRandomOrder()->first() ?? Pharmacist::factory()->create();
            return [
                'status' => 'sent_to_pharmacy',
                'pharmacist_id' => $pharmacist->id,
            ];
        });
    }
}


