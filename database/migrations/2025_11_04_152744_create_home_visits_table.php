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
            $table->foreignId('care_provider_id')
                ->nullable() // This will be NULL until a nurse/physio accepts the request
                ->constrained('care_providers')
                ->cascadeOnDelete();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->enum('service_type', ['nurse', 'physiotherapist']);
            $table->string('reason')->nullable();
            $table->enum('status', [
                'pending',  // waiting for nurse to accept
                'accepted', 
                'in_progress', 
                'completed', 
                'cancelled'
            ])->default('pending');
            $table->string('address')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_visits');
    }
};
