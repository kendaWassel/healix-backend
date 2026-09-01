<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets HealixConversationService::sendMessage() tell a bare reply to a
 * pending follow-up question ("نعم"/"لا") apart from a genuinely new
 * message, without re-classifying every single turn through
 * safety_gate.classify() — that classifier only ever sees the raw message
 * text with no conversation history, so a bare elliptical reply gets
 * misread as a contentless general question (reproduced directly against
 * the live endpoint). Health-education classification is now skipped for
 * exactly one case: last_stage === 'followup' (a question is pending) —
 * every other case (first message, or a turn that ended in diagnosis/
 * crisis/emergency) still gets classified.
 *
 * Thread-scoped last-write-wins snapshot, same nature as the sibling
 * is_crisis/severity/red_flags columns added by
 * 2026_08_20_114916_add_healix_safety_fields_to_conversations_table —
 * that migration's own comment deliberately left this column out because
 * nothing needed it yet; this is the first real need for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('last_stage')->nullable()->after('red_flags');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('last_stage');
        });
    }
};
