<?php

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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->enum('triage', ['High', 'Medium', 'Low']);
            $table->string('recommended_specialty');
            $table->json('possible_diseases')->nullable();
            $table->json('extracted_symptoms')->nullable();
            $table->boolean('emergency_detected')->default(false);
            $table->string('emergency_type')->nullable();
            $table->text('risk_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
