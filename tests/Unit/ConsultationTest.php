<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Consultation;

class ConsultationTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic unit test example.
     */
    public function test_example(): void
    {
        $this->assertTrue(true);
    }
    public function test_patient_can_book_consultation()
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $consultation = Consultation::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDays(2),
            'status' => 'pending',
        ]);

        $this->assertEquals('pending', $consultation->status);

        $this->assertDatabaseHas('consultations', [
            'id' => $consultation->id,
            'patient_id' => $patient->id,
        ]);
    }
    public function test_doctor_can_view_consultation()
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $consultation = Consultation::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDays(2),
            'status' => 'pending',
        ]);

        $this->assertEquals($doctor->id, $consultation->doctor_id);
    }
    public function test_admin_can_view_all_consultations()
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $consultation = Consultation::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDays(2),
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('consultations', [
            'id' => $consultation->id,
        ]);
    }
}
