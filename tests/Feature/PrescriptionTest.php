<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Consultation;
use App\Models\Prescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_example(): void
    {
        $this->assertTrue(true);
    }

    
}