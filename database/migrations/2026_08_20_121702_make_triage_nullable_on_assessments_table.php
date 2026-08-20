<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * assessments.triage was ENUM(['High','Medium','Low']) NOT NULL with no
 * default (2026_06_10_214103_create_assessments_table.php) — fine for the
 * retired legacy MedicalAssistantService pipeline, which always derived a
 * real value from the Python service's urgency.level. Healix has no
 * equivalent source: non-emergency severity grading is unimplemented on
 * that side (CLAUDE.md > Known limitations, Python repo). None of the
 * three existing enum values means "not graded" — storing one anyway would
 * fabricate a severity judgment, so
 * HealixConversationService::persistDiagnosisOutcome writes null instead,
 * which this migration is what makes legal at the schema level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->enum('triage', ['High', 'Medium', 'Low'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->enum('triage', ['High', 'Medium', 'Low'])->nullable(false)->change();
        });
    }
};
