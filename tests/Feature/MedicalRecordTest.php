<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\MedicalRecord;
use App\Models\Upload;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MedicalRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_medical_record_belongs_to_many_uploads()
    {
        // Create multiple uploads
        $upload1 = Upload::factory()->create();
        $upload2 = Upload::factory()->create();

        // Create a medical record
        $medicalRecord = MedicalRecord::factory()->create();

        // Attach uploads to the medical record
        $medicalRecord->attachments()->attach([$upload1->id, $upload2->id]);

        // Assert the relationship works
        $this->assertCount(2, $medicalRecord->attachments);
        $this->assertTrue($medicalRecord->attachments->contains($upload1));
        $this->assertTrue($medicalRecord->attachments->contains($upload2));
    }

    public function test_medical_record_can_have_no_attachments()
    {
        // Create a medical record without attachments
        $medicalRecord = MedicalRecord::factory()->create();

        // Assert attachments collection is empty
        $this->assertCount(0, $medicalRecord->attachments);
    }
}