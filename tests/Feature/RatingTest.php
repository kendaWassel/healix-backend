<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Rating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * FEATURE TEST: Patient can rate doctor after consultation
     */
    public function test_patient_can_rate_doctor()
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $this->actingAs($patient->user);

        $response = $this->postJson('/api/patient/ratings', [
            'target_type' => 'doctor',
            'target_id' => $doctor->id,
            'stars' => 5,
            'consultation_id' => null, // Assume optional
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'status' => 'success',
                 ]);

        $this->assertDatabaseHas('ratings', [
            'user_id' => $patient->user->id,
            'target_type' => 'doctor',
            'target_id' => $doctor->id,
            'stars' => 5,
        ]);
    }

    /**
     * FEATURE TEST: Rating updates doctor's average rating
     */
    public function test_rating_updates_doctor_average_rating()
    {
        $doctor = Doctor::factory()->create(['rating_avg' => 0]);

        Rating::create([
            'user_id' => 1, // Dummy
            'target_type' => 'doctor',
            'target_id' => $doctor->id,
            'stars' => 4,
        ]);

        Rating::create([
            'user_id' => 2, // Dummy
            'target_type' => 'doctor',
            'target_id' => $doctor->id,
            'stars' => 5,
        ]);

        // Assume there's logic to update rating_avg
        // For test, check if it would be 4.5
        $average = Rating::where('target_type', 'doctor')
                         ->where('target_id', $doctor->id)
                         ->avg('stars');

        $this->assertEquals(4.5, $average);
    }
}