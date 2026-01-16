<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Consultation;
use App\Models\Prescription;
use App\Models\PrescriptionMedication;
use App\Models\Medication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * FEATURE TEST: Doctor can create prescription for completed consultation
     */
    public function test_doctor_can_create_prescription_for_completed_consultation()
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();

        $consultation = Consultation::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'schedule',
            'status' => 'completed',
            'scheduled_at' => now()->subDays(1),
        ]);

        $this->actingAs($doctor->user);

        $response = $this->postJson('/api/doctor/prescriptions', [
            'consultation_id' => $consultation->id,
            'diagnosis' => 'Hypertension',
            'notes' => 'Patient needs regular checkups',
            'medicines' => [
                [
                    'name' => 'Aspirin',
                    'boxes' => 2,
                    'dosage' => '1 tablet daily',
                    'instructions' => 'Take with food',
                ],
            ],
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'message' => 'Prescription created',
                 ]);

        $this->assertDatabaseHas('prescriptions', [
            'consultation_id' => $consultation->id,
            'diagnosis' => 'Hypertension',
        ]);

        $this->assertDatabaseHas('medications', [
            'name' => 'Aspirin',
        ]);

        $this->assertDatabaseHas('prescription_medications', [
            'boxes' => 2,
        ]);
    }

    /**
     * FEATURE TEST: Doctor cannot create prescription for pending consultation
     */
    public function test_doctor_cannot_create_prescription_for_pending_consultation()
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();

        $consultation = Consultation::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'schedule',
            'status' => 'pending',
            'scheduled_at' => now()->addDays(1),
        ]);

        $this->actingAs($doctor->user);

        $response = $this->postJson('/api/doctor/prescriptions', [
            'consultation_id' => $consultation->id,
            'medicines' => [
                ['name' => 'Paracetamol', 'boxes' => 1],
            ],
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Only completed consultations can have prescriptions.',
                 ]);
    }

    /**
     * FEATURE TEST: Patient can view their prescriptions
     */
    public function test_patient_can_view_their_prescriptions()
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();

        $consultation = Consultation::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'schedule',
            'status' => 'completed',
            'scheduled_at' => now()->subDays(1),
        ]);

        $prescription = Prescription::create([
            'consultation_id' => $consultation->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Flu',
            'notes' => 'Rest and fluids',
            'source' => 'doctor_written',
        ]);

        $medication = Medication::create([
            'name' => 'Ibuprofen',
            'dosage' => '200mg',
        ]);

        PrescriptionMedication::create([
            'prescription_id' => $prescription->id,
            'medication_id' => $medication->id,
            'boxes' => 1,
            'instructions' => '200mg every 6 hours',
        ]);

        $this->actingAs($patient->user);

        $response = $this->getJson('/api/patient/prescriptions');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'items' => [
                             '*' => [
                                 'id',
                                 'diagnosis',
                                 'status',
                             ],
                         ],
                         'meta',
                     ],
                 ]);
    }

    /**
     * FEATURE TEST: Pharmacist can list prescriptions
     */
    public function test_pharmacist_can_list_prescriptions()
    {
        $pharmacist = \App\Models\Pharmacist::factory()->create();

        // Create a prescription
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $consultation = Consultation::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => 'completed',
        ]);
        $prescription = Prescription::create([
            'consultation_id' => $consultation->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);

        $this->actingAs($pharmacist->user);

        $response = $this->getJson('/api/pharmacist/prescriptions');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data',
                 ]);
    }
}