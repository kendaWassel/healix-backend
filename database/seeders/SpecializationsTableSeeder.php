<?php

  namespace Database\Seeders;

  use App\Models\Specialization;
  use Illuminate\Database\Seeder;

  class SpecializationsTableSeeder extends Seeder
  {
      public function run()
      {
          $specializations = [
              'Dermatology',
              'Cardiology',
              'Orthopedics',
              'Pediatrics',
              'Neurology',
              'Oncology',
              'Ophthalmology',
              'General Surgery',
              'Psychiatry',
              'Gynecology',
          ];
          

          foreach ($specializations as $specialization) {
              Specialization::create([
                  'name' => $specialization,
              ]);
          }
      }
  }