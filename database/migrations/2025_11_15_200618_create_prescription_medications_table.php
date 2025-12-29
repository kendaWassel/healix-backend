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
            $table->string('boxes')->nullable();      //the same as quantity 
            $table->string('instructions')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            // total price can be calculated as boxes * medication price
            $table->decimal('total_price', 10, 2)->nullable();
            //total quantity can be derived from boxes * medication per box
            $table->integer('total_quantity')->nullable();

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
