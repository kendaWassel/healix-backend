<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * JSON array of values from the frontend's PRE_EXISTING_CONDITIONS
     * picker. Separate from chronic_diseases (DrugCentral-standard names
     * used by the DDI condition-check) — this field feeds the lab
     * analysis's pre-existing-condition matching only.
     */
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->json('pre_existing_conditions')->nullable()->after('chronic_diseases');
        });
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropColumn('pre_existing_conditions');
        });
    }
};
