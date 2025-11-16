<?php

namespace Database\Seeders;

use App\Models\Pharmacist;
use Illuminate\Database\Seeder;

class PharmacistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 10 pharmacists with their associated users
        Pharmacist::factory()->count(10)->create();
    }
}

