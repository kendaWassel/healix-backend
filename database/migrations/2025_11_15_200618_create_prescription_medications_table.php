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
        Schema::create('prescription_medications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medication_id')->nullable()->constrained()->nullOnDelete();
            $table->string('boxes')->nullable();      
            $table->string('instructions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescription_medications');
    }
};
