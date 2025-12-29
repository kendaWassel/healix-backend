<?php

namespace Database\Factories;

use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\Upload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MedicalRecord>
 */
class MedicalRecordFactory extends Factory
{
    protected $model = MedicalRecord::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'treatment_plan' => fake()->optional(0.8)->paragraph(),
            'diagnosis' => fake()->optional(0.8)->sentence(),
            'chronic_diseases' => fake()->optional(0.6)->sentence(),
            'previous_surgeries' => fake()->optional(0.5)->sentence(),
            'allergies' => fake()->optional(0.7)->word(),
            'current_medications' => fake()->optional(0.8)->sentence(),
        ];
    }
    
    /**
     * Configure the model factory to attach uploads after creation.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (MedicalRecord $medicalRecord) {
            // Create 0-5 uploads and attach them to the medical record
            if (fake()->boolean(70)) {
                $uploads = Upload::factory()
                    ->count(fake()->numberBetween(1, 5))
                    ->create(['category' => 'medical_record']);
                
                $medicalRecord->attachments()->attach($uploads->pluck('id'));
            }
        });
    }
}
