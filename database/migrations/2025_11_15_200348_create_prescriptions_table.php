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
                'created', //when prescription is created by doctor
                'sent_to_pharmacy', //when patient sends to pharmacy
                'pending', //being processed by pharmacy
                'accepted',
                'priced', 
                'rejected'
            ])->default('created');

            // if patient uploaded paper prescription instead of doctor writing
            $table->foreignId('prescription_image_id')->nullable()->constrained('uploads')->nullOnDelete();
            //total quantity and price of all medications in the prescription
            $table->integer('total_quantity')->nullable();
            $table->decimal('total_price', 10, 2)->nullable();
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
