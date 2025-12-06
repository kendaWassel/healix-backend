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
        // optionally create attachments and return their ids
        $attachmentsCount = $this->faker->numberBetween(0, 3);
        $attachments = null;

        if ($attachmentsCount > 0) {
            $attachments = Upload::factory()->count($attachmentsCount)->create()->pluck('id')->toArray();
        }

        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'treatment_plan' => $this->faker->paragraph(),
            'diagnosis' => $this->faker->sentence(),
            'attachments_id' => $attachments,
            'chronic_diseases' => $this->faker->sentence(),
            'previous_surgeries' => $this->faker->sentence(),
            'allergies' => $this->faker->word(),
            'current_medications' => $this->faker->sentence(),
        ];
    }
}
