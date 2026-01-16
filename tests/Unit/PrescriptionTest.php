<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Prescription;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\Pharmacist;
use App\Policies\PrescriptionPolicy;
use Mockery;

class PrescriptionTest extends TestCase
{
    /**
     * UNIT TEST: Permission rules for viewing prescriptions
     */
    public function test_patient_can_view_own_prescription()
    {
        $policy = new PrescriptionPolicy();
        $patient = Mockery::mock(Patient::class)->makePartial();
        $patient->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user = Mockery::mock(User::class)->makePartial();
        $user->role = 'patient';
        $user->shouldReceive('getAttribute')->with('patient')->andReturn($patient);
        $prescription = new Prescription(['patient_id' => 1]);

        $this->assertTrue($policy->view($user, $prescription));
    }

    /**
     * UNIT TEST: Permission rules for viewing prescriptions
     */
    public function test_patient_cannot_view_other_patient_prescription()
    {
        $policy = new PrescriptionPolicy();
        $patient = Mockery::mock(Patient::class)->makePartial();
        $patient->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user = Mockery::mock(User::class)->makePartial();
        $user->role = 'patient';
        $user->shouldReceive('getAttribute')->with('patient')->andReturn($patient);
        $prescription = new Prescription(['patient_id' => 2]);

        $this->assertFalse($policy->view($user, $prescription));
    }

    /**
     * UNIT TEST: Permission rules for viewing prescriptions
     */
    public function test_doctor_can_view_own_prescription()
    {
        $policy = new PrescriptionPolicy();
        $doctor = Mockery::mock(Doctor::class)->makePartial();
        $doctor->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user = Mockery::mock(User::class)->makePartial();
        $user->role = 'doctor';
        $user->shouldReceive('getAttribute')->with('doctor')->andReturn($doctor);
        $prescription = new Prescription(['doctor_id' => 1]);

        $this->assertTrue($policy->view($user, $prescription));
    }

    /**
     * UNIT TEST: Permission rules for viewing prescriptions
     */
    public function test_pharmacist_can_view_assigned_prescription()
    {
        $policy = new PrescriptionPolicy();
        $pharmacist = Mockery::mock(Pharmacist::class)->makePartial();
        $pharmacist->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user = Mockery::mock(User::class)->makePartial();
        $user->role = 'pharmacist';
        $user->shouldReceive('getAttribute')->with('pharmacist')->andReturn($pharmacist);
        $prescription = new Prescription(['pharmacist_id' => 1]);

        $this->assertTrue($policy->view($user, $prescription));
    }

    /**
     * UNIT TEST: Permission rules for creating prescriptions
     */
    public function test_doctor_can_create_prescriptions()
    {
        $policy = new PrescriptionPolicy();
        $user = new User(['role' => 'doctor']);

        $this->assertTrue($policy->create($user));
    }

    /**
     * UNIT TEST: Permission rules for creating prescriptions
     */
    public function test_patient_cannot_create_prescriptions()
    {
        $policy = new PrescriptionPolicy();
        $user = new User(['role' => 'patient']);

        $this->assertFalse($policy->create($user));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}