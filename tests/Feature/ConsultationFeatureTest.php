<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Services\ConsultationService;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Consultation;
use Carbon\Carbon;

class ConsultationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_book_scheduled_consultation()
    {
        Notification::fake();

        $doctor = Doctor::factory()->create([
            'from' => '09:00:00',
            'to' => '17:00:00',
        ]);

        $patient = Patient::factory()->create();
        $user = $patient->user;

        $this->actingAs($user);

        $service = new ConsultationService();

        $scheduledAt = Carbon::now()->addDays(1)->setTime(10, 0, 0)->toDateTimeString();

        $validated = [
            'doctor_id' => $doctor->id,
            'call_type' => 'schedule',
            'scheduled_at' => $scheduledAt,
        ];

        $consultation = $service->bookConsultation($validated);

        $this->assertEquals('pending', $consultation->status);
        $this->assertDatabaseHas('consultations', [
            'id' => $consultation->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);
    }

    public function test_call_now_throws_when_doctor_busy()
    {
        Notification::fake();

        $doctor = Doctor::factory()->create([
            'from' => '00:00:00',
            'to' => '23:59:59',
        ]);

        $patient = Patient::factory()->create();
        $user = $patient->user;
        $this->actingAs($user);

        // Create an active consultation for the doctor
        Consultation::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'call_now',
            'status' => 'in_progress',
            'scheduled_at' => Carbon::now(),
        ]);

        $service = new ConsultationService();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Doctor is currently busy with another consultation.');

        $validated = [
            'doctor_id' => $doctor->id,
            'call_type' => 'call_now',
        ];

        $service->bookConsultation($validated);
    }
}
