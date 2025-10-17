<?php

use App\Models\Doctor;
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
            $table->foreignIdFor(Doctor::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->text('treatment_plan');
            $table->text('diagnosis');
            $table->string('attachments', 255);
            $table->text('chronic_diseases');
            $table->text('previous_surgeries');
            $table->text('allergies');
            $table->text('current_medications');
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
