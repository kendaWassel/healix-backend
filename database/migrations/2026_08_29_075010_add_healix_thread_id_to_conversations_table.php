<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Fixes a confirmed, reproduced bug: HealixConversationService::sendMessage()
 * used `(string) $conversation->id` as the LangGraph thread_id. That id is
 * larasvel's resettable auto-increment primary key, while
 * healix_checkpoints.sqlite (the Python side's LangGraph checkpointer) is
 * NEVER cleared on `migrate:fresh`. A brand-new conversation that happens to
 * reuse a small integer id that some earlier, unrelated conversation already
 * used silently inherits that old thread's entire accumulated state
 * (symptoms, thread_outcome, sometimes an already-completed diagnosis) —
 * reproduced directly by inspecting healix_checkpoints.sqlite, which had
 * dozens of checkpoint rows already stored under ids like '4', '11', '13'.
 *
 * The fix: give every conversation its own globally-unique thread id that
 * never collides across a database reset, independent of the row's
 * auto-increment id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('healix_thread_id')->nullable()->unique()->after('id');
        });

        // Backfill existing rows so no conversation is ever left without one.
        DB::table('conversations')->whereNull('healix_thread_id')->orderBy('id')->each(function ($conversation) {
            DB::table('conversations')
                ->where('id', $conversation->id)
                ->update(['healix_thread_id' => (string) Str::uuid()]);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('healix_thread_id');
        });
    }
};
