<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Consultation;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ConsultationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $doctors = Doctor::all();
        // $patients = User::where('role', 'patient')->get();

        // if ($doctors->isEmpty() || $patients->isEmpty()) {
        //     $this->command->warn('No doctors or patients found. Please run DoctorSeeder and PatientSeeder first.');
        //     return;
        // }

        // // Create 50 consultations
        // for ($i = 0; $i < 50; $i++) {
        //     Consultation::factory()->create([
        //         'doctor_id' => $doctors->random()->id,
        //         'patient_id' => $patients->random()->id,
        //     ]);
        // }
        Consultation::factory()->count(2)->create();
        
    }

}
