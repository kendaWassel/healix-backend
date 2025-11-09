<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('careprovider_id')->constrained('care_providers')->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->enum('service_type', ['nurse', 'physiotherapist']);
            $table->text('service');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'completed', 'cancelled']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_visits');
    }
};
