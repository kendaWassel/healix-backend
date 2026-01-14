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
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'care_provider_id' => null,
            'consultation_id' => Consultation::factory(),
            'scheduled_at' => fake()->dateTimeBetween('+1 days', '+30 days'),
            'service_type' => $serviceType,
            'reason' => $serviceType === 'nurse' ? 'Nursing Care' : 'Physiotherapy Session',
            'status' => 'pending', // Default status
            'started_at' => null,
            'ended_at' => null,
        ];
    }

    /**
     * Indicate that the home visit is pending
     */
    public function pending()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
                'care_provider_id' => null,
            ];
        });
    }

    /**
     * Indicate that the home visit has been accepted by care provider
     */
    public function accepted()
    {
        return $this->state(function (array $attributes) {
            $serviceType = $attributes['service_type'] ?? fake()->randomElement(['nurse', 'physiotherapist']);
            $careProvider = CareProvider::where('type', $serviceType)
                ->inRandomOrder()
                ->first() ?? CareProvider::factory()->create(['type' => $serviceType]);
            
            return [
                'status' => 'accepted',
                'care_provider_id' => $careProvider->id,
            ];
        });
    }

    /**
     * Indicate that the home visit is in progress
     */
    public function inProgress()
    {
        return $this->state(function (array $attributes) {
            $serviceType = $attributes['service_type'] ?? fake()->randomElement(['nurse', 'physiotherapist']);
            $careProvider = CareProvider::where('type', $serviceType)
                ->inRandomOrder()
                ->first() ?? CareProvider::factory()->create(['type' => $serviceType]);
            
            return [
                'status' => 'in_progress',
                'care_provider_id' => $careProvider->id,
                'started_at' => now(),
            ];
        });
    }

    /**
     * Indicate that the home visit is completed
     */
    public function completed()
    {
        return $this->state(function (array $attributes) {
            $serviceType = $attributes['service_type'] ?? fake()->randomElement(['nurse', 'physiotherapist']);
            $careProvider = CareProvider::where('type', $serviceType)
                ->inRandomOrder()
                ->first() ?? CareProvider::factory()->create(['type' => $serviceType]);
            
            $startedAt = now()->subHours(2);
            return [
                'status' => 'completed',
                'care_provider_id' => $careProvider->id,
                'started_at' => $startedAt,
                'ended_at' => $startedAt->copy()->addHours(1),
            ];
        });
    }

    /**
     * Indicate that the home visit is cancelled
     */
    public function cancelled()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'cancelled',
                'care_provider_id' => fake()->optional()->randomElement(
                    CareProvider::pluck('id')->toArray()
                ),
            ];
        });
    }
}
