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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

                $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
                $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();

                $table->enum('status', [
                    'sent', 
                    'accepted', 
                    'rejected', 
                    'ready',
                    'waiting_pickup',
                    'out_for_delivery',
                    'delivered'
                ])->default('sent');

                $table->text('pharmacist_notes')->nullable();
                $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
