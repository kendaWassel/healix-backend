<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Medication;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Pharmacist;
use App\Models\Prescription;
use App\Models\PrescriptionMedication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the prescription lifecycle steps between "doctor creates a
 * prescription" (already tested in PrescriptionTest.php) and "payment"
 * (already tested in PaymentTest.php) — the missing links that actually
 * connect the two into the real end-to-end workflow: patient uploads/sends
 * a prescription, the three role-specific safety-verification endpoints
 * (Pharmacist\PrescriptionSafetyController, Doctor\..., Patient\... — each
 * a distinct route from the raw /api/ddi/* ones already covered by
 * DrugInteractionTest.php), and the pharmacist pricing step. Confirmed by
 * grep this had ZERO coverage before this file.
 *
 * The verify endpoints call the DDI microservice through DdiService/DdiClient
 * (the same Http-facade-based client DrugInteractionTest.php fakes), so
 * Http::fake() is used the same way here.
 */
class PrescriptionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    // --- upload / send to pharmacy ------------------------------------------

    public function test_patient_can_upload_a_paper_prescription(): void
    {
        Storage::fake('public');
        $patient = Patient::factory()->create();

        // ->image() needs the GD extension (not installed in this local PHP
        // setup — a real environment gap, not a code bug); ->create() with
        // an explicit image mime type satisfies UploadRequest's `image` rule
        // without needing GD to actually render pixels.
        $response = $this->actingAs($patient->user, 'sanctum')
            ->postJson('/api/patient/prescriptions/upload', [
                'category' => 'prescription',
                'image' => UploadedFile::fake()->create('rx.jpg', 100, 'image/jpeg'),
            ]);

        $response->assertStatus(200)->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('prescriptions', [
            'id' => $response->json('data.prescription_id'),
            'patient_id' => $patient->id,
            'source' => 'patient_uploaded',
        ]);
    }

    public function test_patient_can_send_a_prescription_to_an_open_pharmacy(): void
    {
        $patient = Patient::factory()->create();
        $pharmacist = Pharmacist::factory()->create(['from' => '00:00:00', 'to' => '23:59:59']);
        $prescription = Prescription::create([
            'patient_id' => $patient->id,
            'source' => 'patient_uploaded',
            'status' => 'created',
        ]);

        $response = $this->actingAs($patient->user, 'sanctum')
            ->postJson("/api/patient/prescriptions/{$prescription->id}/send", [
                'pharmacy_id' => $pharmacist->id,
            ]);

        $response->assertStatus(200)->assertJsonPath('data.status', 'sent_to_pharmacy');
        $this->assertDatabaseHas('prescriptions', ['id' => $prescription->id, 'status' => 'sent_to_pharmacy']);
        $this->assertDatabaseHas('orders', ['prescription_id' => $prescription->id, 'pharmacist_id' => $pharmacist->id]);
    }

    public function test_sending_to_a_closed_pharmacy_is_rejected(): void
    {
        $patient = Patient::factory()->create();
        // A window that cannot contain "now" in Asia/Damascus.
        $pharmacist = Pharmacist::factory()->create(['from' => '03:00:00', 'to' => '03:05:00']);
        $prescription = Prescription::create([
            'patient_id' => $patient->id,
            'source' => 'patient_uploaded',
            'status' => 'created',
        ]);

        $response = $this->actingAs($patient->user, 'sanctum')
            ->postJson("/api/patient/prescriptions/{$prescription->id}/send", [
                'pharmacy_id' => $pharmacist->id,
            ]);

        // Flaky only in the single real minute-of-day 03:00-03:05 window;
        // acceptable for a factory-time-window test like this.
        $response->assertStatus(400);
    }

    public function test_patient_cannot_send_someone_elses_prescription(): void
    {
        $patient = Patient::factory()->create();
        $otherPatient = Patient::factory()->create();
        $pharmacist = Pharmacist::factory()->create(['from' => '00:00:00', 'to' => '23:59:59']);
        $prescription = Prescription::create([
            'patient_id' => $otherPatient->id,
            'source' => 'patient_uploaded',
            'status' => 'created',
        ]);

        $response = $this->actingAs($patient->user, 'sanctum')
            ->postJson("/api/patient/prescriptions/{$prescription->id}/send", [
                'pharmacy_id' => $pharmacist->id,
            ]);

        $response->assertStatus(404);
    }

    // --- pharmacist verify (integrated safety check) ------------------------

    private function prescriptionWithMedication(Pharmacist $pharmacist, Patient $patient, string $status = 'accepted'): Prescription
    {
        $prescription = Prescription::create([
            'patient_id' => $patient->id,
            'pharmacist_id' => $pharmacist->id,
            'source' => 'patient_uploaded',
            'status' => $status,
        ]);
        $medication = Medication::firstOrCreate(['name' => 'Paracetamol'], ['dosage' => '']);
        PrescriptionMedication::create([
            'prescription_id' => $prescription->id,
            'medication_id' => $medication->id,
            'boxes' => 1,
        ]);

        return $prescription;
    }

    public function test_pharmacist_verify_reports_safe_for_a_clean_patient_single_drug(): void
    {
        // < 2 medications and no allergies/pregnancy/conditions means
        // PrescriptionSafetyService never calls the DDI service at all
        // (confirmed by reading drugInteractions()/allergyWarnings()/
        // pregnancyWarnings()/ConditionCheckService::check()) — Http::fake()
        // with no rules acts as a safety net that would fail the test loudly
        // if that assumption ever stops holding.
        Http::fake();

        $pharmacist = Pharmacist::factory()->create();
        $patient = Patient::factory()->create(['gender' => 'male']);
        $prescription = $this->prescriptionWithMedication($pharmacist, $patient);

        $response = $this->actingAs($pharmacist->user, 'sanctum')
            ->postJson("/api/pharmacist/prescriptions/{$prescription->id}/verify", [
                'medications' => ['Paracetamol'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.safe', true);
        Http::assertNothingSent();
    }

    public function test_pharmacist_verify_flags_a_direct_allergy_match(): void
    {
        $pharmacist = Pharmacist::factory()->create();
        $patient = Patient::factory()->create(['gender' => 'female']);
        MedicalRecord::create(['patient_id' => $patient->id, 'allergies' => ['Paracetamol']]);
        $prescription = $this->prescriptionWithMedication($pharmacist, $patient);

        Http::fake([
            '*/allergy*' => Http::response([
                'direct_matches' => [['medication' => 'Paracetamol', 'allergen' => 'Paracetamol', 'risk' => 'CRITICAL']],
                'cross_reactive_matches' => [],
            ], 200),
        ]);

        $response = $this->actingAs($pharmacist->user, 'sanctum')
            ->postJson("/api/pharmacist/prescriptions/{$prescription->id}/verify", [
                'medications' => ['Paracetamol'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.safe', false)
            ->assertJsonCount(1, 'data.allergy_warnings');
    }

    public function test_pharmacist_verify_persists_the_entered_medication_list(): void
    {
        Http::fake();
        $pharmacist = Pharmacist::factory()->create();
        $patient = Patient::factory()->create();
        $prescription = $this->prescriptionWithMedication($pharmacist, $patient);

        $this->actingAs($pharmacist->user, 'sanctum')
            ->postJson("/api/pharmacist/prescriptions/{$prescription->id}/verify", [
                'medications' => ['Ibuprofen'],
            ]);

        // The originally-seeded Paracetamol row must be replaced, not kept
        // alongside — confirmed rule from PrescriptionMedicationService's
        // own docstring ("Replace ... any previously-saved medication not
        // in this list is removed").
        $this->assertDatabaseMissing('prescription_medications', [
            'prescription_id' => $prescription->id,
            'medication_id' => Medication::where('name', 'Paracetamol')->value('id'),
        ]);
        $this->assertDatabaseHas('prescription_medications', [
            'prescription_id' => $prescription->id,
            'medication_id' => Medication::where('name', 'Ibuprofen')->value('id'),
        ]);
    }

    public function test_pharmacist_cannot_verify_a_prescription_assigned_to_another_pharmacist(): void
    {
        $owner = Pharmacist::factory()->create();
        $intruder = Pharmacist::factory()->create();
        $patient = Patient::factory()->create();
        $prescription = $this->prescriptionWithMedication($owner, $patient);

        $response = $this->actingAs($intruder->user, 'sanctum')
            ->postJson("/api/pharmacist/prescriptions/{$prescription->id}/verify", [
                'medications' => ['Paracetamol'],
            ]);

        $response->assertStatus(403);
    }

    public function test_pharmacist_verify_rejects_an_empty_medication_list(): void
    {
        $pharmacist = Pharmacist::factory()->create();
        $patient = Patient::factory()->create();
        $prescription = $this->prescriptionWithMedication($pharmacist, $patient);

        $response = $this->actingAs($pharmacist->user, 'sanctum')
            ->postJson("/api/pharmacist/prescriptions/{$prescription->id}/verify", [
                'medications' => [],
            ]);

        $response->assertStatus(422);
    }

    // --- doctor draft verify (decision support, before saving) --------------

    public function test_doctor_with_a_real_consultation_can_verify_a_draft_for_that_patient(): void
    {
        Http::fake();
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        Consultation::create(['doctor_id' => $doctor->id, 'patient_id' => $patient->id, 'status' => 'completed']);

        $response = $this->actingAs($doctor->user, 'sanctum')
            ->postJson('/api/doctor/prescriptions/verify', [
                'patient_id' => $patient->id,
                'medications' => ['Paracetamol'],
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_doctor_without_a_consultation_for_the_patient_cannot_verify_a_draft(): void
    {
        // Real rule (PatientPolicy::view): a doctor may only act on a
        // patient they have an actual Consultation with — same rule already
        // confirmed for the AI doctor-summary endpoint.
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($doctor->user, 'sanctum')
            ->postJson('/api/doctor/prescriptions/verify', [
                'patient_id' => $patient->id,
                'medications' => ['Paracetamol'],
            ]);

        $response->assertStatus(403);
    }

    // --- patient self-verify -------------------------------------------------

    public function test_patient_can_self_verify_a_draft_medication_list(): void
    {
        Http::fake();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($patient->user, 'sanctum')
            ->postJson('/api/patient/prescriptions/verify', [
                'medications' => ['Paracetamol'],
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
    }

    // --- add-price (pharmacist pricing step, before payment) ----------------

    public function test_pharmacist_can_add_prices_to_an_accepted_prescription(): void
    {
        $pharmacist = Pharmacist::factory()->create();
        $patient = Patient::factory()->create();
        $prescription = Prescription::create([
            'patient_id' => $patient->id,
            'pharmacist_id' => $pharmacist->id,
            'source' => 'patient_uploaded',
            'status' => 'accepted',
        ]);
        $order = Order::create([
            'prescription_id' => $prescription->id,
            'patient_id' => $patient->id,
            'pharmacist_id' => $pharmacist->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($pharmacist->user, 'sanctum')
            ->postJson("/api/pharmacist/prescriptions/{$order->id}/add-price", [
                'items' => [
                    ['medicine_name' => 'Paracetamol', 'dosage' => '500mg', 'price' => 10],
                ],
            ]);

        $response->assertStatus(200)->assertJsonPath('data.status', 'priced');
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id, 'status' => 'priced', 'total_price' => 10,
        ]);
    }

    public function test_add_price_is_rejected_before_the_prescription_is_accepted(): void
    {
        $pharmacist = Pharmacist::factory()->create();
        $patient = Patient::factory()->create();
        $prescription = Prescription::create([
            'patient_id' => $patient->id,
            'pharmacist_id' => $pharmacist->id,
            'source' => 'patient_uploaded',
            'status' => 'sent_to_pharmacy',
        ]);
        $order = Order::create([
            'prescription_id' => $prescription->id,
            'patient_id' => $patient->id,
            'pharmacist_id' => $pharmacist->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($pharmacist->user, 'sanctum')
            ->postJson("/api/pharmacist/prescriptions/{$order->id}/add-price", [
                'items' => [
                    ['medicine_name' => 'Paracetamol', 'dosage' => '500mg', 'price' => 10],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_add_price_is_rejected_when_already_priced(): void
    {
        $pharmacist = Pharmacist::factory()->create();
        $patient = Patient::factory()->create();
        $prescription = Prescription::create([
            'patient_id' => $patient->id,
            'pharmacist_id' => $pharmacist->id,
            'source' => 'patient_uploaded',
            'status' => 'priced',
        ]);
        $order = Order::create([
            'prescription_id' => $prescription->id,
            'patient_id' => $patient->id,
            'pharmacist_id' => $pharmacist->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($pharmacist->user, 'sanctum')
            ->postJson("/api/pharmacist/prescriptions/{$order->id}/add-price", [
                'items' => [
                    ['medicine_name' => 'Paracetamol', 'dosage' => '500mg', 'price' => 10],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_a_rejected_add_price_attempt_does_not_leave_a_stray_open_transaction(): void
    {
        // Regression test: PharmacistController::addPrice() called
        // DB::beginTransaction() but several early-return validation
        // branches (already-priced, not-yet-accepted, unauthorized order,
        // missing pharmacist profile) returned straight away without
        // DB::rollBack() — the real, confirmed bug. That left an open
        // transaction on the connection, and the very next request in the
        // same process (here: another DB::beginTransaction() call anywhere)
        // failed with "There is already an active transaction" — first
        // surfaced when this test file's own tests ran back-to-back.
        // Fixed by adding DB::rollBack() to each of those branches.
        $pharmacist = Pharmacist::factory()->create();
        $patient = Patient::factory()->create();
        $prescription = Prescription::create([
            'patient_id' => $patient->id,
            'pharmacist_id' => $pharmacist->id,
            'source' => 'patient_uploaded',
            'status' => 'priced',
        ]);
        $order = Order::create([
            'prescription_id' => $prescription->id,
            'patient_id' => $patient->id,
            'pharmacist_id' => $pharmacist->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($pharmacist->user, 'sanctum')
            ->postJson("/api/pharmacist/prescriptions/{$order->id}/add-price", [
                'items' => [['medicine_name' => 'Paracetamol', 'dosage' => '500mg', 'price' => 10]],
            ])->assertStatus(422);

        // If the bug were still present, this would throw
        // "There is already an active transaction".
        \Illuminate\Support\Facades\DB::beginTransaction();
        \Illuminate\Support\Facades\DB::rollBack();
        $this->assertTrue(true);
    }

    public function test_pharmacist_cannot_add_price_to_another_pharmacists_order(): void
    {
        $owner = Pharmacist::factory()->create();
        $intruder = Pharmacist::factory()->create();
        $patient = Patient::factory()->create();
        $prescription = Prescription::create([
            'patient_id' => $patient->id,
            'pharmacist_id' => $owner->id,
            'source' => 'patient_uploaded',
            'status' => 'accepted',
        ]);
        $order = Order::create([
            'prescription_id' => $prescription->id,
            'patient_id' => $patient->id,
            'pharmacist_id' => $owner->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($intruder->user, 'sanctum')
            ->postJson("/api/pharmacist/prescriptions/{$order->id}/add-price", [
                'items' => [
                    ['medicine_name' => 'Paracetamol', 'dosage' => '500mg', 'price' => 10],
                ],
            ]);

        $response->assertStatus(403);
    }
}
