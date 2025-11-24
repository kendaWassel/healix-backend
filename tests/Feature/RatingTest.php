<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Consultation;
use App\Models\Specialization;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RatingTest extends TestCase
{
    use RefreshDatabase;

    private function createDoctorWithUser(): array
    {
        $spec = Specialization::create(['name' => 'Cardiology']);
        $doctorUser = User::factory()->create(['role' => 'doctor']);
        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'specialization_id' => $spec->id,
            'from' => '09:00:00',
            'to' => '17:00:00',
            'consultation_fee' => 150,
            'bank_account' => '1234567890',
            'rating_avg' => 0,
        ]);
        return [$doctorUser, $doctor];
    }

    private function createPatientWithUser(): array
    {
        $patientUser = User::factory()->create(['role' => 'patient']);
        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'birth_date' => '1990-01-01',
            'gender' => 'male',
            'address' => '123 Street',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ]);
        return [$patientUser, $patient];
    }

    public function test_patient_can_rate_doctor_after_completed_consultation(): void
    {
        [$doctorUser, $doctor] = $this->createDoctorWithUser();
        [$patientUser, $patient] = $this->createPatientWithUser();

        $consultation = Consultation::create([
            'patient_id' => $patientUser->id,
            'doctor_id' => $doctor->id,
            'type' => 'schedule',
            'status' => 'completed',
            'scheduled_at' => now(),
        ]);

        $this->actingAs($patientUser, 'sanctum')
            ->postJson('/api/patient/ratings/doctors/' . $doctor->id, [
                'consultation_id' => $consultation->id,
                'stars' => 5,
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.consultation_id', $consultation->id)
            ->assertJsonPath('data.doctor_id', $doctor->id);

        $doctor->refresh();
        $this->assertEquals(5.0, (float) $doctor->rating_avg);
    }

    public function test_patient_cannot_rate_before_completion(): void
    {
        [$doctorUser, $doctor] = $this->createDoctorWithUser();
        [$patientUser, $patient] = $this->createPatientWithUser();

        $consultation = Consultation::create([
            'patient_id' => $patientUser->id,
            'doctor_id' => $doctor->id,
            'type' => 'schedule',
            'status' => 'in_progress',
            'scheduled_at' => now(),
        ]);

        $this->actingAs($patientUser, 'sanctum')
            ->postJson('/api/patient/ratings/doctors/' . $doctor->id, [
                'consultation_id' => $consultation->id,
                'stars' => 4,
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }
}

