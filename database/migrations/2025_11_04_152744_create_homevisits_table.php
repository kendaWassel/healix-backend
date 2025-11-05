<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_visits', function (Blueprint $table) {
            $table->id('visit_id');
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by_doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('careprovider_id')->constrained('care_providers')->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->enum('service_type', ['nurse', 'physiotherapist']);
            $table->enum('status', ['pending', 'accepted', 'rejected', 'completed', 'cancelled']);
            $table->decimal('fee', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_visits');
    }
};
