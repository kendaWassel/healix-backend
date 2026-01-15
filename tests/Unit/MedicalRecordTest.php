<?php

namespace Tests\Unit;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\MedicalRecord;
use PHPUnit\Framework\TestCase;


class MedicalRecordTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_example(): void
    {
        $this->assertTrue(true);
    }
    public function test_doctor_can_update_medical_record()
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $medicalRecord = MedicalRecord::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'treatment_plan' => 'Initial treatment plan',
            'diagnosis' => 'Initial diagnosis',
        ]);

        $medicalRecord->update([
            'treatment_plan' => 'Updated treatment plan',
            'diagnosis' => 'Updated diagnosis',
        ]);

        $this->assertEquals('Updated treatment plan', $medicalRecord->treatment_plan);
        $this->assertEquals('Updated diagnosis', $medicalRecord->diagnosis);

        $this->assertDatabaseHas('medical_records', [
            'id' => $medicalRecord->id,
            'treatment_plan' => 'Updated treatment plan',
            'diagnosis' => 'Updated diagnosis',
        ]);
    }
    
}
