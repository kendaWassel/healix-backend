<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Normalized storage for symptoms extracted by MARBERT during an interview.
 * One row per distinct symptom in a conversation (the JSON blob on messages
 * remains untouched for backward compatibility).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_symptoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();
            $table->string('symptom_text');
            $table->boolean('negated')->default(false);
            $table->float('confidence')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_symptoms');
    }
};
