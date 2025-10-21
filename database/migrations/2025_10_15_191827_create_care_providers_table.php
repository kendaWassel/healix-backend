<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('care_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedBigInteger('care_provider_image_id')->nullable();
            $table->unsignedBigInteger('license_file_id')->nullable();            
            $table->decimal('session_fee', 10, 2);
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->enum('type', ['nurse', 'physiotherapist'])->nullable();
            $table->string('bank_account')->nullable();
            $table->decimal('rating_avg', 2, 1)->default(0);
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('care_providers');
    }
};
