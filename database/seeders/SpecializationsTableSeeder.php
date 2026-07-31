<?php

  namespace Database\Seeders;

  use App\Models\Specialization;
  use Illuminate\Database\Seeder;

  class SpecializationsTableSeeder extends Seeder
  {
      public function run()
      {
          // `name` stays the canonical English value and the stable lookup key
          // used by doctor registration and by the AI service's
          // recommended_specialty. `name_ar` is display-only.
          $specializations = [
              ['name' => 'Dermatology',     'name_ar' => 'الأمراض الجلدية'],
              ['name' => 'Cardiology',      'name_ar' => 'أمراض القلب'],
              ['name' => 'Orthopedics',     'name_ar' => 'جراحة العظام'],
              ['name' => 'Pediatrics',      'name_ar' => 'طب الأطفال'],
              ['name' => 'Neurology',       'name_ar' => 'الأمراض العصبية'],
              ['name' => 'Oncology',        'name_ar' => 'الأورام'],
              ['name' => 'Ophthalmology',   'name_ar' => 'طب العيون'],
              ['name' => 'General Surgery', 'name_ar' => 'الجراحة العامة'],
              ['name' => 'Psychiatry',      'name_ar' => 'الطب النفسي'],
              ['name' => 'Gynecology',      'name_ar' => 'أمراض النساء والولادة'],
              ['name' => 'Urology',         'name_ar' => 'المسالك البولية'],
          ];

          // updateOrCreate keeps the seeder re-runnable: it backfills name_ar on
          // existing rows instead of failing the unique constraint on name.
          foreach ($specializations as $specialization) {
              Specialization::updateOrCreate(
                  ['name' => $specialization['name']],
                  ['name_ar' => $specialization['name_ar']],
              );
          }
      }
  }
