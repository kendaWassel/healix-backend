<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Specialization;
use App\Models\User;
use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('role', 'doctor')->first();

        $specialization = Specialization::first();
        if (! $specialization) {
            $specialization = Specialization::create(['name' => 'General']);
        }

        if ($user) {
            Doctor::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'specialization_id' => $specialization->id,
                    'gender' => 'male',
                    'doctor_image_id' => null,
                    'from' => '00:00:00',
                    'to' => '23:59:59',
                    'certificate_file_id' => null,
                    'consultation_fee' => 50.00,
                    'bank_account' => null,
                ]
            );
        }
        Doctor::create([
            'user_id' => User::where('role', 'doctor')->first()->id,
            'specialization_id' => 1,
            'gender' => 'male',
            'doctor_image_id' => null,
            'from' => '09:00:00',
            'to' => '17:00:00',
            'certificate_file_id' => null,
        ]); 


        // Create additional doctors via factory
        Doctor::factory()->count(10)->create();
    }
}

