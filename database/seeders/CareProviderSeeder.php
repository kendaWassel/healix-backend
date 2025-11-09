<?php

namespace Database\Seeders;

use App\Models\CareProvider;
use Illuminate\Database\Seeder;

class CareProviderSeeder extends Seeder
{

    public function run(): void
    {
        // Create 5 nurses
        CareProvider::factory()->count(5)->state([
            'type' => 'nurse',
        ])->create();

        // Create 5 physiotherapists
        CareProvider::factory()->count(5)->state([
            'type' => 'physiotherapist',
        ])->create();
    }
}

