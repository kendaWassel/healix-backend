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
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('target_type', ['doctor', 'pharmacist', 'care_provider', 'delivery']);
            $table->unsignedInteger('target_id');
            $table->tinyInteger('stars')->check('stars BETWEEN 1 AND 5');
            $table->unique(['user_id', 'target_type', 'target_id'], 'unique_rating');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
