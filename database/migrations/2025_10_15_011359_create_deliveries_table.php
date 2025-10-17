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
         Schema::create('deliveries', function (Blueprint $table) {
            $table->id('delivery_id'); 
            $table->string('full_name', 255);
            $table->string('email', 255)->unique();
            $table->string('phone', 50);
            $table->string('photo', 255)->nullable();
            $table->string('vehicle_type', 50);
            $table->string('plate_number', 50);
            $table->string('bank_account', 255);
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $table->string('password', 255);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
