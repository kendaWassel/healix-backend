<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the existing (previously unused) doctor_summaries table into the
 * official short medical report for the doctor, instead of creating a
 * duplicate `medical_reports` table.
 *
 * `patient_id` is filled immediately (known from the conversation as soon as
 * the assessment finishes); `doctor_id` starts null and is filled once the
 * patient actually books a consultation (see ConsultationService::bookConsultation()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_summaries', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->after('assessment_id')
                ->constrained('patients')->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->after('patient_id')
                ->constrained('doctors')->nullOnDelete();
            $table->string('status')->default('draft')->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_summaries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('doctor_id');
            $table->dropConstrainedForeignId('patient_id');
            $table->dropColumn('status');
        });
    }
};
