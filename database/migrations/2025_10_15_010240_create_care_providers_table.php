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
        Schema::create('careProvider', function (Blueprint $table) {
            $table->id('careprovider_id'); 
            $table->string('full_name', 255);
            $table->enum('type', ['nurse', 'physiotherapist']);
            $table->integer('age');
            $table->string('certificate', 255);
            $table->string('email', 255)->unique();
            $table->string('phone', 50);
            $table->string('photo', 255)->nullable();
            $table->string('bank_account', 255);
            $table->decimal('session_fee', 10, 2);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->string('password', 255);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations. 
     */
    public function down(): void
    {
        Schema::dropIfExists('careProviders');
    }
};
