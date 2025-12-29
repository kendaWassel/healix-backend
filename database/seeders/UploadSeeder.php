<?php
namespace Database\Seeders;
use App\Models\Upload;
use Illuminate\Database\Seeder;

class UploadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create uploads
        Upload::factory()->count(50)->create();
    }
}