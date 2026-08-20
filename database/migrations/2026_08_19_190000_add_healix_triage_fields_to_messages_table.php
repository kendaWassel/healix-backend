<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Healix AI triage fields — previously computed per-turn and
            // returned only in HealixConversationService::sendMessage()'s
            // JSON response, never persisted, so a patient reopening a
            // finished conversation (GET .../messages) lost the diagnosis,
            // specialty, and reports entirely. Nullable/false-default: a
            // message from the OTHER assistant (MedicalAssistantService)
            // or an older row never has this, and that must read as
            // "nothing here", not an error.
            $table->boolean('is_crisis')->default(false)->after('status');
            $table->string('severity')->nullable()->after('is_crisis');
            $table->json('diagnosis')->nullable()->after('severity');
            $table->string('specialty')->nullable()->after('diagnosis');
            $table->json('reports')->nullable()->after('specialty');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['is_crisis', 'severity', 'diagnosis', 'specialty', 'reports']);
        });
    }
};
