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
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();

            $table->string('diagnosis')->nullable();
            $table->text('notes')->nullable();  // Notes for patient/pharmacy

            $table->enum('status', [
                'created', 
                'sent_to_pharmacy',
                'accepted',
                'rejected',
                'ready',
                'out_for_delivery',
                'delivered',
                'completed'
            ])->default('created');

            // if patient uploaded paper prescription instead of doctor writing
            $table->unsignedBigInteger('prescription_file_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
