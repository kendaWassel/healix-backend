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

            $table->string('name');     
            $table->string('dosage');      // 500mg
            $table->string('form')->nullable();// tablet, syrup, injection
            $table->string('quantity')->nullable();
            $table->string('frequency')->nullable();// e.g., "twice a day"
            $table->string('duration')->nullable();// 5 days, 2 weeks
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
