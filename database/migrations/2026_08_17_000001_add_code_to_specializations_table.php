<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a stable English slug ("code") to specializations — the AI service
 * (healix-ai-medical-assistant) resolves each recommended specialty to a
 * {code, name_ar} pair from its own dictionary (app/dictionaries/specialty_labels.yaml)
 * and returns it on every assessment. Storing the same code here lets
 * Assessment.specialty_code be a simple denormalized echo of what the AI
 * returned, resolvable back to a real Specialization row without any string
 * matching at read time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('specializations', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('specializations', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
