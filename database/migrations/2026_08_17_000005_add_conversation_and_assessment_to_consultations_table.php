<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a consultation back to the AI conversation/assessment it originated
 * from, when the patient booked via the "book with recommended specialty"
 * flow. Both nullable — the existing direct-booking path (no AI involved)
 * keeps working exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('doctor_id')
                ->constrained('conversations')->nullOnDelete();
            $table->foreignId('assessment_id')->nullable()->after('conversation_id')
                ->constrained('assessments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assessment_id');
            $table->dropConstrainedForeignId('conversation_id');
        });
    }
};
