<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check uploads
echo 'Total uploads: ' . App\Models\Upload::count() . PHP_EOL;
echo 'Uploads with category profile: ' . App\Models\Upload::where('category', 'profile')->count() . PHP_EOL;

$uploads = App\Models\Upload::all();
echo 'All uploads:' . PHP_EOL;
foreach ($uploads as $upload) {
    echo 'ID: ' . $upload->id . ', category: ' . $upload->category . ', file_path: ' . ($upload->file_path ?? 'null') . PHP_EOL;
}

echo PHP_EOL;

// Check all doctors with images
$doctorsWithImages = App\Models\Doctor::whereNotNull('doctor_image_id')->get();
echo 'Doctors with images: ' . $doctorsWithImages->count() . PHP_EOL;

foreach ($doctorsWithImages as $doc) {
    echo 'Doctor ID: ' . $doc->id . ', doctor_image_id: ' . $doc->doctor_image_id . PHP_EOL;
    $upload = App\Models\Upload::find($doc->doctor_image_id);
    if ($upload) {
        echo '  Upload file_path: ' . ($upload->file_path ?? 'null') . PHP_EOL;
        echo '  Asset URL: ' . asset('storage/' . $upload->file_path) . PHP_EOL;
    } else {
        echo '  Upload not found!' . PHP_EOL;
    }
    echo PHP_EOL;
}

// Check doctor ID 2 specifically
$doctor2 = App\Models\Doctor::find(2);
if ($doctor2) {
    echo 'Doctor ID 2 - doctor_image_id: ' . ($doctor2->doctor_image_id ?? 'null') . PHP_EOL;
} else {
    echo 'Doctor ID 2 not found' . PHP_EOL;
}