<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Doctor;
use App\Policies\ConsultationPolicy;
use Mockery;

class ConsultationTest extends TestCase
{
    /**
     * UNIT TEST: Permission rules for viewing consultations
     */
    public function test_patient_can_view_own_consultation()
    {
        $policy = new ConsultationPolicy();
        $patient = Mockery::mock(Patient::class)->makePartial();
        $patient->user_id = 1;
        $consultation = Mockery::mock(Consultation::class)->makePartial();
        $consultation->patient = $patient;
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->role = 'patient';

        $this->assertTrue($policy->view($user, $consultation));
    }

    /**
     * UNIT TEST: Permission rules for viewing consultations
     */
    public function test_patient_cannot_view_other_patient_consultation()
    {
        $policy = new ConsultationPolicy();
        $patient = Mockery::mock(Patient::class)->makePartial();
        $patient->user_id = 2;
        $consultation = Mockery::mock(Consultation::class)->makePartial();
        $consultation->patient = $patient;
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->role = 'patient';

        $this->assertFalse($policy->view($user, $consultation));
    }

    /**
     * UNIT TEST: Permission rules for viewing consultations
     */
    public function test_doctor_can_view_own_consultation()
    {
        $policy = new ConsultationPolicy();
        $doctor = Mockery::mock(Doctor::class)->makePartial();
        $doctor->id = 1;
        $user = Mockery::mock(User::class)->makePartial();
        $user->role = 'doctor';
        $user->shouldReceive('getAttribute')->with('doctor')->andReturn($doctor);
        $consultation = Mockery::mock(Consultation::class)->makePartial();
        $consultation->doctor_id = 1;

        $this->assertTrue($policy->view($user, $consultation));
    }

    /**
     * UNIT TEST: Permission rules for viewing consultations
     */
    public function test_doctor_cannot_view_other_doctor_consultation()
    {
        $policy = new ConsultationPolicy();
        $doctor = Mockery::mock(Doctor::class)->makePartial();
        $doctor->id = 1;
        $user = Mockery::mock(User::class)->makePartial();
        $user->role = 'doctor';
        $user->shouldReceive('getAttribute')->with('doctor')->andReturn($doctor);
        $consultation = Mockery::mock(Consultation::class)->makePartial();
        $consultation->doctor_id = 2;

        $this->assertFalse($policy->view($user, $consultation));
    }

    /**
     * UNIT TEST: Permission rules for creating consultations
     */
    public function test_patient_can_create_consultations()
    {
        $policy = new ConsultationPolicy();
        $user = new User(['role' => 'patient']);

        $this->assertTrue($policy->create($user));
    }

    /**
     * UNIT TEST: Permission rules for creating consultations
     */
    public function test_doctor_cannot_create_consultations()
    {
        $policy = new ConsultationPolicy();
        $user = new User(['role' => 'doctor']);

        $this->assertFalse($policy->create($user));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
