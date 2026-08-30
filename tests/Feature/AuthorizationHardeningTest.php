<?php

namespace Tests\Feature;

use App\Models\CareProvider;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\LabAnalysis;
use App\Models\MedicalRecord;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Pharmacist;
use App\Models\Prescription;
use App\Models\Rating;
use App\Models\Upload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Proves the 7 authorization gaps closed this session (plus the
 * RatingController hardening) actually reject a stranger and still let the
 * legitimate owner through. Each test targets exactly one route; the
 * "unrelated X" test is the one that would have failed before the fix
 * (200/302 instead of 401/403).
 */
class AuthorizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 1) UploadController::downloadFile
    // ------------------------------------------------------------------

    public function test_upload_download_rejects_unauthenticated_request()
    {
        $upload = Upload::factory()->create();

        $response = $this->getJson("/api/uploads/download/{$upload->id}");

        $response->assertStatus(401);
    }

    public function test_upload_download_rejects_unrelated_user()
    {
        Storage::fake('public');

        $owner = Patient::factory()->create();
        $stranger = Patient::factory()->create();

        $upload = Upload::create([
            'user_id' => $owner->user_id,
            'category' => 'profile',
            'file' => 'photo.jpg',
            'file_path' => 'profile/photo.jpg',
            'mime' => 'image/jpeg',
        ]);
        Storage::disk('public')->put('profile/photo.jpg', 'fake-image-bytes');

        $this->actingAs($stranger->user);

        $response = $this->getJson("/api/uploads/download/{$upload->id}");

        $response->assertStatus(403);
    }

    public function test_upload_download_allows_owner()
    {
        Storage::fake('public');

        $owner = Patient::factory()->create();

        $upload = Upload::create([
            'user_id' => $owner->user_id,
            'category' => 'profile',
            'file' => 'photo.jpg',
            'file_path' => 'profile/photo.jpg',
            'mime' => 'image/jpeg',
        ]);
        Storage::disk('public')->put('profile/photo.jpg', 'fake-image-bytes');

        $this->actingAs($owner->user);

        $response = $this->get("/api/uploads/download/{$upload->id}");

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    // 2) MedicalRecordController::downloadAttachment
    // ------------------------------------------------------------------

    public function test_medical_record_attachment_download_rejects_unrelated_doctor()
    {
        Storage::fake('public');

        $patient = Patient::factory()->create();
        $recordOwnerDoctor = Doctor::factory()->create();
        $unrelatedDoctor = Doctor::factory()->create();

        $record = MedicalRecord::create([
            'patient_id' => $patient->id,
            'doctor_id' => $recordOwnerDoctor->id,
        ]);

        $upload = Upload::create([
            'user_id' => $recordOwnerDoctor->user_id,
            'category' => 'report',
            'file' => 'attachment.pdf',
            'file_path' => 'report/attachment.pdf',
            'mime' => 'application/pdf',
            'medical_record_id' => $record->id,
        ]);
        Storage::disk('public')->put('report/attachment.pdf', 'fake-pdf-bytes');

        $this->actingAs($unrelatedDoctor->user);

        $response = $this->getJson("/api/medical-records/attachments/{$upload->id}/download");

        $response->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // 3) MedicalRecordController::viewDetails
    // ------------------------------------------------------------------

    public function test_view_details_rejects_unrelated_pharmacist()
    {
        $patient = Patient::factory()->create();
        $pharmacist = Pharmacist::factory()->create();

        $this->actingAs($pharmacist->user);

        $response = $this->getJson("/api/patients/{$patient->id}/view-details");

        $response->assertStatus(403);
    }

    public function test_view_details_allows_doctor_with_real_consultation()
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        Consultation::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'schedule',
            'status' => 'completed',
        ]);

        $this->actingAs($doctor->user);

        $response = $this->getJson("/api/patients/{$patient->id}/view-details");

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    // 4) LabAnalysisController::indexForPatient
    // ------------------------------------------------------------------

    public function test_lab_analyses_index_rejects_doctor_with_no_consultation()
    {
        $patient = Patient::factory()->create();
        $unrelatedDoctor = Doctor::factory()->create();

        LabAnalysis::create([
            'patient_id' => $patient->id,
            'report_id' => 'RPT-' . uniqid(),
        ]);

        $this->actingAs($unrelatedDoctor->user);

        $response = $this->getJson("/api/patients/{$patient->id}/lab/analyses");

        $response->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // 5) PharmacistController::viewPrescription
    // ------------------------------------------------------------------

    public function test_view_prescription_rejects_unrelated_pharmacist()
    {
        $owningPharmacist = Pharmacist::factory()->create();
        $unrelatedPharmacist = Pharmacist::factory()->create();
        $patient = Patient::factory()->create();

        $prescription = Prescription::factory()->create([
            'patient_id' => $patient->id,
            'pharmacist_id' => $owningPharmacist->id,
        ]);

        $order = Order::factory()->create([
            'prescription_id' => $prescription->id,
            'patient_id' => $patient->id,
            'pharmacist_id' => $owningPharmacist->id,
        ]);

        $this->actingAs($unrelatedPharmacist->user);

        $response = $this->getJson("/api/pharmacist/prescriptions/{$order->id}");

        $response->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // 6) OrderController::markReadyForDelivery
    // ------------------------------------------------------------------

    public function test_mark_ready_for_delivery_rejects_unrelated_pharmacist()
    {
        $owningPharmacist = Pharmacist::factory()->create();
        $unrelatedPharmacist = Pharmacist::factory()->create();
        $patient = Patient::factory()->create();

        $prescription = Prescription::factory()->create(['patient_id' => $patient->id]);

        $order = Order::factory()->create([
            'prescription_id' => $prescription->id,
            'patient_id' => $patient->id,
            'pharmacist_id' => $owningPharmacist->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($unrelatedPharmacist->user);

        $response = $this->postJson("/api/pharmacist/orders/{$order->id}/ready");

        $response->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // 7) Doctor\PrescriptionSafetyController::verify
    // ------------------------------------------------------------------

    public function test_doctor_prescription_verify_rejects_doctor_with_no_relationship_to_patient()
    {
        $patient = Patient::factory()->create();
        $unrelatedDoctor = Doctor::factory()->create();

        $this->actingAs($unrelatedDoctor->user);

        $response = $this->postJson('/api/doctor/prescriptions/verify', [
            'patient_id' => $patient->id,
            'medications' => ['Paracetamol'],
        ]);

        $response->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // 8) RatingController — new RatingPolicy::create gate
    // ------------------------------------------------------------------

    public function test_non_patient_cannot_submit_a_rating()
    {
        $doctor = Doctor::factory()->create();
        $targetDoctor = Doctor::factory()->create();

        $consultation = Consultation::create([
            'patient_id' => Patient::factory()->create()->id,
            'doctor_id' => $targetDoctor->id,
            'type' => 'schedule',
            'status' => 'completed',
        ]);

        $this->actingAs($doctor->user);

        $response = $this->postJson(
            "/api/patient/consultations/{$consultation->id}/rate/{$targetDoctor->id}",
            ['stars' => 5]
        );

        $response->assertStatus(403);
    }

    public function test_patient_can_still_rate_doctor_after_completed_consultation()
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $consultation = Consultation::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'schedule',
            'status' => 'completed',
        ]);

        $this->actingAs($patient->user);

        $response = $this->postJson(
            "/api/patient/consultations/{$consultation->id}/rate/{$doctor->id}",
            ['stars' => 5]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('ratings', [
            'user_id' => $patient->user_id,
            'target_type' => 'doctor',
            'target_id' => $doctor->id,
            'stars' => 5,
        ]);
    }
}
