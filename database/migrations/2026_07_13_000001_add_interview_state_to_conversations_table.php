<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds interview state to conversations so Laravel becomes the source of truth
 * for the AI history-taking interview:
 *  - session_id: the id returned by the stateless Python interview engine.
 *  - status: the interview lifecycle status (active/completed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('session_id')->nullable()->after('patient_id')->index();
            $table->string('status')->default('active')->after('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['session_id', 'status']);
        });
    }
};
