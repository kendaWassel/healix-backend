<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\MedicalRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalRecordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * FEATURE TEST: Patient can view their medical record
     */
    public function test_patient_can_view_their_medical_record()
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $medicalRecord = MedicalRecord::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Hypertension',
            'treatment_plan' => 'Medication and lifestyle changes',
        ]);

        $this->actingAs($patient->user);

        $response = $this->getJson('/api/patient/medical-record');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'diagnosis',
                         'treatment_plan',
                     ],
                 ]);
    }

    /**
     * FEATURE TEST: Doctor can update patient's medical record
     */
    public function test_doctor_can_update_patient_medical_record()
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $medicalRecord = MedicalRecord::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Initial diagnosis',
        ]);

        $this->actingAs($doctor->user);

        $response = $this->putJson("/api/doctor/{$patient->id}/medical-record/update", [
            'diagnosis' => 'Updated diagnosis',
            'treatment_plan' => 'New treatment plan',
            'chronic_diseases' => 'Diabetes',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'message' => 'Medical record updated successfully.',
                 ]);

        $this->assertDatabaseHas('medical_records', [
            'id' => $medicalRecord->id,
            'diagnosis' => 'Updated diagnosis',
            'treatment_plan' => 'New treatment plan',
            'chronic_diseases' => 'Diabetes',
        ]);
    }

    /**
     * FEATURE TEST: Doctor cannot update medical record of another doctor's patient
     */
    public function test_doctor_cannot_update_other_doctor_patient_medical_record()
    {
        $patient = Patient::factory()->create();
        $doctor1 = Doctor::factory()->create();
        $doctor2 = Doctor::factory()->create();

        MedicalRecord::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor1->id,
        ]);

        $this->actingAs($doctor2->user);

        $response = $this->putJson("/api/doctor/{$patient->id}/medical-record/update", [
            'diagnosis' => 'Unauthorized update',
        ]);

        $response->assertStatus(403);
    }
}