<?php

namespace Database\Factories;

use App\Models\Upload;
use App\Models\User;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories.Factory<\App\Models\Upload>
 */
class UploadFactory extends Factory
{
    protected $model = Upload::class;

    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'category'   => fake()->randomElement(['prescription', 'medical_record', 'profile']),
            'file'       => fake()->uuid() . '.jpg',
            'file_path'  => 'uploads/' . fake()->uuid() . '.jpg',
            'mime'       => 'image/jpeg',
        ];
    }
}


