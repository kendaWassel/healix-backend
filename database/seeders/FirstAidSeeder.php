<?php

namespace Database\Seeders;

use App\Models\FirstAid;
use Illuminate\Database\Seeder;

class FirstAidSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FirstAid::truncate();

        $firstAids = [
            [
                'title' => 'CPR (Adults)',
                'description' => "1) Call emergency services. 2) Push hard and fast in the center of the chest (100–120/min, ~5–6 cm deep). 3) Allow full recoil. 4) If trained, give 30 compressions then 2 rescue breaths. 5) Use an AED when available and follow its prompts.",
            ],
            [
                'title' => 'Choking (Adults/Children)',
                'description' => "1) If they cannot breathe or speak, give 5 back blows. 2) Then give 5 abdominal thrusts (Heimlich). 3) Alternate back blows and thrusts until the object is expelled or they become unresponsive. 4) If unresponsive, start CPR and check the mouth before breaths.",
            ],
            [
                'title' => 'Choking (Infants)',
                'description' => "1) Support face-down with head lower than chest. 2) Give 5 back slaps. 3) Turn face-up and give 5 chest thrusts with two fingers. 4) Repeat cycles until clear or unresponsive, then start infant CPR.",
            ],
            [
                'title' => 'Burns (Minor)',
                'description' => "1) Cool under running water for 10–20 minutes. 2) Do not use ice, butter, or ointments. 3) Cover with a sterile non-stick dressing. 4) Seek care for large, deep, or sensitive-area burns.",
            ],
            [
                'title' => 'Severe Bleeding',
                'description' => "1) Apply firm direct pressure with a clean cloth. 2) Add more layers if soaked—do not remove the first. 3) Elevate if no fracture suspected. 4) Consider a tourniquet for life-threatening limb bleeding. 5) Call emergency services.",
            ],
            [
                'title' => 'Nosebleed',
                'description' => "1) Sit up and lean slightly forward. 2) Pinch the soft part of the nose for 10–15 minutes. 3) Apply a cold compress. 4) Avoid blowing or picking the nose afterward. 5) Seek help if bleeding lasts longer than 20 minutes.",
            ],
            [
                'title' => 'Sprains and Strains',
                'description' => "1) Rest and protect the area. 2) Ice 15–20 minutes every 2–3 hours for 48 hours. 3) Compress with an elastic bandage. 4) Elevate above heart level. 5) Begin gentle movement as pain allows.",
            ],
            [
                'title' => 'Fainting',
                'description' => "1) Lay the person flat and elevate legs. 2) Loosen tight clothing. 3) If unresponsive or breathing is abnormal, call emergency services and start CPR.",
            ],
            [
                'title' => 'Stroke (FAST)',
                'description' => "Face drooping, Arm weakness, Speech difficulty—Time to call emergency services immediately.",
            ],
            [
                'title' => 'Heart Attack (Suspected)',
                'description' => "1) Call emergency services. 2) Sit and keep calm. 3) If not allergic and low bleeding risk, consider giving aspirin to chew (160–325 mg). 4) Be ready to start CPR/AED if needed.",
            ],
        ];

        foreach ($firstAids as $firstAid) {
            FirstAid::create($firstAid);
        }
    }
}
