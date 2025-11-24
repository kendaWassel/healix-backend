<?php

use App\Models\User;
use App\Models\Specialization;
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
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(Specialization::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->unsignedBigInteger('doctor_image_id')->nullable();
            $table->time('from');
            $table->time('to');
            $table->unsignedBigInteger('certificate_file_id')->nullable();
            $table->decimal('consultation_fee', 10, 2)->nullable();
            $table->string('bank_account')->nullable();
            $table->decimal('rating_avg', 2, 1)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
