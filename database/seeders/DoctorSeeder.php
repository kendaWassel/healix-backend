<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Specialization;
use Illuminate\Container\Attributes\DB;
use Illuminate\Database\Seeder;
use PhpParser\Comment\Doc;

class DoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $specializations = Specialization::all();

        if ($specializations->isEmpty()) {
            $this->command->warn('No specializations found. Please run SpecializationsTableSeeder first.');
            return;
        }

        // Create 15 doctors with their associated users and specializations
        for ($i = 0; $i < 15; $i++) {
            Doctor::factory()->create([
                'specialization_id' => $specializations->random()->id,
            ]);
        }
        //create new doctor
  
        

    }
}

