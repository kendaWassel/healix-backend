<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\MedicalRecord;
use App\Models\Upload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    /**
     * FEATURE TEST: a doctor who never authored this patient's record, but
     * has a real (booked) consultation with them, can still view it —
     * MedicalRecordPolicy::view's added consultation-based branch.
     */
    public function test_doctor_with_consultation_but_no_authored_record_can_view_patient_medical_record()
    {
        $patient = Patient::factory()->create();
        $authoringDoctor = Doctor::factory()->create();
        $treatingDoctor = Doctor::factory()->create();

        MedicalRecord::create([
            'patient_id' => $patient->id,
            'doctor_id' => $authoringDoctor->id,
            'diagnosis' => 'Hypertension',
            'treatment_plan' => 'Medication and lifestyle changes',
        ]);

        Consultation::create([
            'patient_id' => $patient->id,
            'doctor_id' => $treatingDoctor->id,
        ]);

        $this->actingAs($treatingDoctor->user);

        $response = $this->getJson("/api/patients/{$patient->id}/view-details");

        $response->assertStatus(200)
                 ->assertJsonPath('data.medical_record.diagnosis', 'Hypertension');
    }

    /**
     * FEATURE TEST: a doctor with NEITHER an authored record NOR any
     * consultation for this patient is rejected — this is the actual gap
     * being closed (view-details previously had no authorization at all).
     */
    public function test_doctor_with_no_relationship_to_patient_cannot_view_medical_record()
    {
        $patient = Patient::factory()->create();
        $authoringDoctor = Doctor::factory()->create();
        $unrelatedDoctor = Doctor::factory()->create();

        MedicalRecord::create([
            'patient_id' => $patient->id,
            'doctor_id' => $authoringDoctor->id,
            'diagnosis' => 'Hypertension',
        ]);

        $this->actingAs($unrelatedDoctor->user);

        $response = $this->getJson("/api/patients/{$patient->id}/view-details");

        $response->assertStatus(403);
    }

    /**
     * FEATURE TEST: attachment download now requires the same
     * relationship check as view-details, for an authorized doctor.
     */
    public function test_doctor_with_consultation_can_download_medical_record_attachment()
    {
        Storage::fake('public');

        $patient = Patient::factory()->create();
        $treatingDoctor = Doctor::factory()->create();

        $record = MedicalRecord::create(['patient_id' => $patient->id]);

        $path = 'medical_records/test-attachment.pdf';
        Storage::disk('public')->put($path, 'dummy content');

        $upload = Upload::create([
            'file' => 'test-attachment.pdf',
            'file_path' => $path,
            'mime' => 'application/pdf',
            'category' => 'medical_record',
            'medical_record_id' => $record->id,
        ]);

        Consultation::create([
            'patient_id' => $patient->id,
            'doctor_id' => $treatingDoctor->id,
        ]);

        $this->actingAs($treatingDoctor->user);

        $response = $this->get("/api/medical-records/attachments/{$upload->id}/download");

        $response->assertStatus(200);
    }

    /**
     * FEATURE TEST: attachment download rejects a doctor with no
     * relationship to the record's patient.
     */
    public function test_doctor_with_no_relationship_cannot_download_medical_record_attachment()
    {
        $patient = Patient::factory()->create();
        $unrelatedDoctor = Doctor::factory()->create();

        $record = MedicalRecord::create(['patient_id' => $patient->id]);

        $upload = Upload::create([
            'file' => 'test-attachment.pdf',
            'file_path' => 'medical_records/test-attachment.pdf',
            'mime' => 'application/pdf',
            'category' => 'medical_record',
            'medical_record_id' => $record->id,
        ]);

        $this->actingAs($unrelatedDoctor->user);

        $response = $this->getJson("/api/medical-records/attachments/{$upload->id}/download");

        $response->assertStatus(403);
    }

    /**
     * FEATURE TEST: the download route used to be fully public
     * (registered outside auth:sanctum) — confirms the route move actually
     * took effect.
     */
    public function test_unauthenticated_request_cannot_download_medical_record_attachment()
    {
        $patient = Patient::factory()->create();
        $record = MedicalRecord::create(['patient_id' => $patient->id]);

        $upload = Upload::create([
            'file' => 'test-attachment.pdf',
            'file_path' => 'medical_records/test-attachment.pdf',
            'mime' => 'application/pdf',
            'category' => 'medical_record',
            'medical_record_id' => $record->id,
        ]);

        $response = $this->getJson("/api/medical-records/attachments/{$upload->id}/download");

        $response->assertStatus(401);
    }
}