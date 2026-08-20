<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Healix (nodes/check_red_flags.py, nodes/crisis_check.py on the Python
 * side) computes is_crisis/severity/red_flags fresh every turn, but until
 * now HealixConversationService only ever returned them in the HTTP
 * response — nothing persisted them. Updated on EVERY successful Healix
 * turn (HealixConversationService::sendMessage's success path only, never
 * the AIServiceException fallback branch, which returns neutral defaults
 * that are explicitly documented as not a real safety verdict), regardless
 * of stage -- a crisis/emergency turn never reaches stage=="diagnosis", so
 * these three columns are the only durable record of that outcome; there is
 * deliberately no Assessment/DoctorSummary row for those turns (see
 * HealixConversationService's own comment at the call site).
 *
 * Thread-scoped snapshot fields, not per-turn history — last-write-wins,
 * same as the Python side's own state["thread_outcome"]/state["severity"]
 * being plain (non-reducer) fields. Deliberately just these three, not a
 * fourth "last_stage" column — nothing in this app currently needs it, and
 * conversations.status already exists for the broader active/completed
 * lifecycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->boolean('is_crisis')->default(false)->after('status');
            $table->string('severity')->nullable()->after('is_crisis');
            $table->json('red_flags')->nullable()->after('severity');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['is_crisis', 'severity', 'red_flags']);
        });
    }
};
