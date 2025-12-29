<?php

use App\Models\MedicalRecord;
use App\Models\Upload;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('medical_record_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(MedicalRecord::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Upload::class)->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Prevent duplicate attachments for the same medical record
            $table->unique(['medical_record_id', 'upload_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_record_uploads');
    }
};
