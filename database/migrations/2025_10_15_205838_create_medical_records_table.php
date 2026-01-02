<?php

use App\Models\Doctor;
use App\Models\Upload;
use App\Models\Patient;
use PhpParser\Comment\Doc;
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
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Patient::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(Doctor::class)->nullable()->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('care_provider_id')->nullable()->constrained('care_providers')->cascadeOnDelete()->cascadeOnUpdate();
            $table->text('treatment_plan')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('chronic_diseases')->nullable();
            $table->text('previous_surgeries')->nullable();
            $table->text('allergies')->nullable();
            $table->text('current_medications')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
