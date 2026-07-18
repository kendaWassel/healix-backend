<?php

use App\Models\Patient;
use App\Models\User;
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
        Schema::create('drug_interaction_checks', function (Blueprint $table) {
            $table->id();
            // Who performed the check (any authenticated user/role).
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            // The patient the check concerns, when the performer is a patient.
            $table->foreignIdFor(Patient::class)->nullable()->constrained()->nullOnDelete();
            // interaction | batch | screen | allergy | pregnancy
            $table->string('check_type');
            // Denormalised summary columns for quick filtering/sorting.
            $table->string('highest_severity')->nullable();
            $table->unsignedInteger('interactions_found')->default(0);
            // Full request/response payloads.
            $table->json('input')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'check_type']);
            $table->index(['patient_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drug_interaction_checks');
    }
};
