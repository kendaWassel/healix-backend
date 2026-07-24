<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores each user's preferred language.
 *
 * Accept-Language only describes the request currently in flight. Queued work —
 * notifications, reminder mails, SMS — runs later on a worker with no request
 * at all, and would otherwise always fall back to the default locale. Laravel
 * reads this column through the HasLocalePreference contract on the User model
 * and renders each notification in the recipient's own language.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'preferred_locale')) {
                $table->string('preferred_locale', 5)->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'preferred_locale')) {
                $table->dropColumn('preferred_locale');
            }
        });
    }
};
