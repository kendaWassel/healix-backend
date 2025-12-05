<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\HomeVisit;
use App\Models\CareProvider;
use App\Models\Consultation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HomeVisit>
 */
class HomeVisitFactory extends Factory
{
    protected $model = HomeVisit::class;

    public function definition(): array

    {
        
        $serviceType = fake()->randomElement(['nurse', 'physiotherapist']);

        return [
            'patient_id' => Patient::factory()->state(fn () => [ 'role' => 'patient' ]),
            'doctor_id' => Doctor::factory(),
            'care_provider_id' => CareProvider::factory()->state(fn () => [ 'type' => $serviceType ]),
            'consultation_id' => Consultation::factory(),
            'scheduled_at' => fake()->dateTimeBetween('+1 days', '+30 days'),
            'service_type' => $serviceType,
            'reason' => $serviceType === 'nurse' ? 'Nursing Care' : 'Physiotherapy Session',
            'status' => fake()->randomElement(['pending', 'accepted', 'rejected', 'completed', 'cancelled']),
        ];
    }
}
