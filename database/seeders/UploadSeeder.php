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
        // Small filler batch only (e.g. profile-picture placeholders) — not
        // a demo scenario in its own right. Note: these rows reference
        // fake file paths with no real file on disk (Upload::factory()'s
        // long-standing behavior), so downloading one 404s; that's a
        // pre-existing gap, not something this pass fixes.
        //
        // Guarded so reruns don't add 10 more every time — factory-created
        // rows have no natural unique key to updateOrCreate against.
        if (Upload::count() < 10) {
            // Override the factory's own 'user_id' => User::factory() default
            // — left alone, that spawns 10 brand-new random-@example.com
            // accounts every run, exactly the factory-generated clutter this
            // whole seeder pass was meant to get rid of. user_id is nullable.
            Upload::factory()->count(10)->create(['user_id' => null]);
        }
    }
}