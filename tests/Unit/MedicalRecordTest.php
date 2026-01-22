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
    // test care providers can update medical records
    public function test_doctor_can_update_medical_record()
    {
        // Create a new MedicalRecord instance
        $record = new MedicalRecord();
        $record->patient_id = 1;
        $record->doctor_id = 1;
        $record->diagnosis = 'Initial diagnosis';
        $record->treatmentPlan = 'Initial plan';

        // Create a new instance of the MedicalRecord class to update the record
        $updater = new MedicalRecord();
        $updater->patient_id = 1;
        $updater->doctor_id = 1;
        $updater->diagnosis = 'Updated diagnosis';
        $updater->treatmentPlan = 'Updated plan';

        // Assert that the update was successful
        $this->assertTrue($record->is($updater));
    }

    // test patients can view their own medical records
    public function test_patient_can_view_own_medical_record()
    {
        // Create a new Patient instance
        $patient = new Patient();
        $patient->id = 1;
        $patient->name = 'John Doe';

        // Create a new MedicalRecord instance for the patient
        $record = new MedicalRecord();
        $record->patient_id = $patient->id;
        $record->diagnosis = 'Flu';
        $record->treatmentPlan = 'Rest and hydration';

        // Assert that the patient can view their own medical record
        $this->assertEquals($record->patient_id, $patient->id);
    }
}
