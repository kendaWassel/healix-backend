<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Doctor;
use App\Policies\MedicalRecordPolicy;
use Mockery;

class MedicalRecordPolicyTest extends TestCase
{
    /**
     * UNIT TEST: Permission rules for viewing medical records
     */
    public function test_patient_can_view_own_medical_record()
    {
        $policy = new MedicalRecordPolicy();
        $patient = Mockery::mock(Patient::class)->makePartial();
        $patient->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user = Mockery::mock(User::class)->makePartial();
        $user->role = 'patient';
        $user->shouldReceive('getAttribute')->with('patient')->andReturn($patient);
        $medicalRecord = new MedicalRecord(['patient_id' => 1]);

        $this->assertTrue($policy->view($user, $medicalRecord));
    }

    /**
     * UNIT TEST: Permission rules for viewing medical records
     */
    public function test_patient_cannot_view_other_patient_medical_record()
    {
        $policy = new MedicalRecordPolicy();
        $patient = Mockery::mock(Patient::class)->makePartial();
        $patient->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user = Mockery::mock(User::class)->makePartial();
        $user->role = 'patient';
        $user->shouldReceive('getAttribute')->with('patient')->andReturn($patient);
        $medicalRecord = new MedicalRecord(['patient_id' => 2]);

        $this->assertFalse($policy->view($user, $medicalRecord));
    }

    /**
     * UNIT TEST: Permission rules for updating medical records
     */
    public function test_doctor_can_update_own_patient_medical_record()
    {
        $policy = new MedicalRecordPolicy();
        $doctor = Mockery::mock(Doctor::class)->makePartial();
        $doctor->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user = Mockery::mock(User::class)->makePartial();
        $user->role = 'doctor';
        $user->shouldReceive('getAttribute')->with('doctor')->andReturn($doctor);
        $medicalRecord = new MedicalRecord(['doctor_id' => 1]);

        $this->assertTrue($policy->update($user, $medicalRecord));
    }

    /**
     * UNIT TEST: Permission rules for updating medical records
     */
    public function test_doctor_cannot_update_other_doctor_patient_medical_record()
    {
        $policy = new MedicalRecordPolicy();
        $doctor = Mockery::mock(Doctor::class)->makePartial();
        $doctor->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user = Mockery::mock(User::class)->makePartial();
        $user->role = 'doctor';
        $user->shouldReceive('getAttribute')->with('doctor')->andReturn($doctor);
        $medicalRecord = new MedicalRecord(['doctor_id' => 2]);

        $this->assertFalse($policy->update($user, $medicalRecord));
    }

    /**
     * UNIT TEST: Permission rules for creating medical records
     */
    public function test_doctor_can_create_medical_records()
    {
        $policy = new MedicalRecordPolicy();
        $user = new User(['role' => 'doctor']);

        $this->assertTrue($policy->create($user));
    }

    /**
     * UNIT TEST: Permission rules for creating medical records
     */
    public function test_patient_cannot_create_medical_records()
    {
        $policy = new MedicalRecordPolicy();
        $user = new User(['role' => 'patient']);

        $this->assertFalse($policy->create($user));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}