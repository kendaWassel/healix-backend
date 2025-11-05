<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('care_provider_id')->constrained('care_providers')->cascadeOnDelete();
            $table->enum('type', ['call_now','schedule'])->default('schedule');
            $table->enum('status', ['pending','accepted','rejected','completed','cancelled'])->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->decimal('fee', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
