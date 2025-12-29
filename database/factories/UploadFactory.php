<?php

namespace Database\Factories;

use App\Models\Upload;
use App\Models\User;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

class UploadFactory extends Factory
{
    protected $model = Upload::class;

    public function definition(): array
    {
        // file or image
        return[
            'user_id' => User::factory(),
            'category' => $this->faker->randomElement(['profile', 'prescription', 'certificate', 'report']),
            'file' => $this->faker->word().'.'.$this->faker->fileExtension(),
            'file_path' => 'uploads/'.$this->faker->word().'.'.$this->faker->fileExtension(),
            'mime' => $this->faker->mimeType(),
        ];
    }
}


