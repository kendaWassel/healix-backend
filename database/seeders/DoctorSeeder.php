<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Specialization;
use App\Models\User;
use Database\Seeders\Data\DemoScenarioData;
use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
{
    public function run(): void
    {
        $fallback = Specialization::first() ?? Specialization::create(['name' => 'General', 'name_ar' => 'طب عام']);
        $cardiology = Specialization::where('name', 'Cardiology')->first() ?? $fallback;

        // The original doctor — Cardiology, matches the specialty the
        // seeded AI-assistant conversations recommend.
        $user = User::where('email', DemoScenarioData::DOCTOR_EMAIL)->first();
        if ($user) {
            $this->upsertDoctor($user, $cardiology->id, 'male', 50.00);
        }

        // One doctor per remaining specialization, all available 24/7
        // (from=00:00, to=23:59) so every specialty is always bookable in
        // the demo.
        $feeByIndex = [60, 70, 55, 90, 100, 65, 120, 80, 75, 85];
        $genderByIndex = ['male', 'female'];

        foreach (array_values(DemoScenarioData::SPECIALIST_DOCTORS) as $index => $specializationName) {
            $email = array_keys(DemoScenarioData::SPECIALIST_DOCTORS)[$index];
            $user = User::where('email', $email)->first();
            if (! $user) {
                continue;
            }

            $specialization = Specialization::where('name', $specializationName)->first() ?? $fallback;

            $this->upsertDoctor(
                $user,
                $specialization->id,
                $genderByIndex[$index % 2],
                $feeByIndex[$index % count($feeByIndex)],
                twentyFourHours: true
            );
        }
    }

    /**
     * updateOrCreate, not firstOrCreate — these are fixed demo doctors, so
     * a rerun must force specialization/hours/fee back to these values,
     * not silently leave whatever an earlier manual test happened to set
     * (this is exactly what went stale before: doctor@gmail.com had drifted
     * to a leftover "Gastroenterology" profile from before this pass).
     */
    protected function upsertDoctor(User $user, int $specializationId, string $gender, float $fee, bool $twentyFourHours = true): void
    {
        Doctor::updateOrCreate(
            ['user_id' => $user->id],
            [
                'specialization_id' => $specializationId,
                'gender' => $gender,
                'from' => $twentyFourHours ? '00:00:00' : '09:00:00',
                'to' => $twentyFourHours ? '23:59:59' : '17:00:00',
                'consultation_fee' => $fee,
            ]
        );
    }
}
