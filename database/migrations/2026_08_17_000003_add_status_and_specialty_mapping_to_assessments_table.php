<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a booking-lifecycle status (pending -> completed -> booked) and an
 * explicit specialty mapping to assessments, so callers stop relying on the
 * free-text `recommended_specialty` string alone.
 *
 * `specialty_id`/`specialty_code`/`specialty_name_ar` are a denormalized
 * snapshot captured at persist time (SpecializationResolver match + the AI
 * service's own patient_summary.specialty), not a live join — a report
 * already shown to a patient/doctor should not silently change if an admin
 * edits a Specialization's name later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('conversation_id');
            $table->foreignId('specialty_id')->nullable()->after('recommended_specialty')
                ->constrained('specializations')->nullOnDelete();
            $table->string('specialty_code')->nullable()->after('specialty_id');
            $table->string('specialty_name_ar')->nullable()->after('specialty_code');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
            $table->dropColumn(['status', 'specialty_code', 'specialty_name_ar']);
        });
    }
};
