<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->boolean('is_pregnant')->default(false)->after('allergies');
            // Trimester 1, 2 or 3; null when not pregnant / not applicable.
            $table->unsignedTinyInteger('pregnancy_trimester')->nullable()->after('is_pregnant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropColumn(['is_pregnant', 'pregnancy_trimester']);
        });
    }
};
