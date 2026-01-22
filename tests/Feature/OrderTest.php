<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Pharmacist;
use App\Models\Consultation;
use App\Models\Prescription;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * FEATURE TEST: Pharmacist can accept prescription order
     */
    public function test_pharmacist_can_accept_prescription_order()
    {
        $pharmacist = Pharmacist::factory()->create();
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
            'status' => 'sent_to_pharmacy',
        ]);

        $order = Order::create([
            'prescription_id' => $prescription->id,
            'pharmacist_id' => $pharmacist->id,
            'patient_id' => $patient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($pharmacist->user);

        $response = $this->postJson("/api/pharmacist/prescriptions/{$prescription->id}/accept");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                 ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'status' => 'accepted',
        ]);
    }

    /**
     * FEATURE TEST: Pharmacist can reject prescription order
     */
    public function test_pharmacist_can_reject_prescription_order()
    {
        $pharmacist = Pharmacist::factory()->create();
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
            'status' => 'sent_to_pharmacy',
        ]);

        $order = Order::create([
            'prescription_id' => $prescription->id,
            'pharmacist_id' => $pharmacist->id,
            'patient_id' => $patient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($pharmacist->user);

        $response = $this->postJson("/api/pharmacist/prescriptions/{$prescription->id}/reject", [
            'rejection_reason' => 'Out of stock',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                 ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'rejected',
            'rejection_reason' => 'Out of stock',
        ]);
    }
}