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
            $table->foreignId('consultation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('pharmacist_id')->nullable()->constrained('pharmacists')->cascadeOnDelete();
            
            $table->string('diagnosis')->nullable();
            $table->text(column: 'notes')->nullable();  // Notes for patient/pharmacy

            //source
            $table->enum('source', [
                'doctor_written',
                'patient_uploaded'
            ])->default('doctor_written');
            
            $table->enum('status', [
                'created', 
                'sent_to_pharmacy',
            ])->default('created');

            // if patient uploaded paper prescription instead of doctor writing
            $table->unsignedBigInteger('prescription_image_id')->nullable();
            //total quantity and price of all medications in the prescription
            $table->integer('total_quantity');
            $table->decimal('total_price', 10, 2);
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
