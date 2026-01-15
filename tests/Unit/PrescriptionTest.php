<?php

namespace Tests\Unit;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Consultation;
use App\Models\Prescription;
use PHPUnit\Framework\TestCase;

class PrescriptionTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_example(): void
    {
        $this->assertTrue(true);
    }
    public function test_doctor_can_create_prescription()
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();
        $consultation = Consultation::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'call_now',
            'scheduled_at' => now()->subDays(1),
            'status' => 'completed',
        ]);

        $prescription = Prescription::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'consultation_id' => $consultation->id,
            'diagnosis' => 'Flu',
            'notes' => 'Drink water',
            'status' => 'created',
        ]);

        $this->assertEquals('created', $prescription->status);
    }

    public function test_patient_can_view_prescription()
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();
        $consultation = Consultation::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'call_now',
            'scheduled_at' => now()->subDays(1),
            'status' => 'completed',
        ]);

        $prescription = Prescription::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'consultation_id' => $consultation->id,
            'diagnosis' => 'Flu',
            'notes' => 'Drink water',
            'status' => 'created',
        ]);

        $fetchedPrescription = Prescription::where('consultation_id', $consultation->id)
            ->where('patient_id', $patient->id)
            ->first();

        $this->assertNotNull($fetchedPrescription);
        $this->assertEquals($prescription->id, $fetchedPrescription->id);
    }
}