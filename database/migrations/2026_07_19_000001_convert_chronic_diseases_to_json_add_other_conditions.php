<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chronic conditions move from free text to a JSON array of DrugCentral-
     * standard English names (chosen from the app's condition picker), so the
     * exact strings survive round-trip and can be matched literally by the
     * DDI /condition-check engine. `other_conditions` is free text shown to the
     * clinician for review but NOT sent to the automatic contraindication check.
     */
    public function up(): void
    {
        // Convert existing free-text values to valid JSON arrays BEFORE the
        // column type changes — MySQL validates JSON on ALTER, so any legacy
        // "Peptic ulcer" / "Diabetes, Asthma" string must already be JSON.
        foreach (DB::table('medical_records')->whereNotNull('chronic_diseases')->get(['id', 'chronic_diseases']) as $row) {
            $raw = trim((string) $row->chronic_diseases);

            if ($raw === '') {
                DB::table('medical_records')->where('id', $row->id)->update(['chronic_diseases' => null]);
                continue;
            }

            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                continue; // already a JSON array
            }

            $parts = preg_split('/[,;\n\r]+/', $raw) ?: [];
            $parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));

            DB::table('medical_records')->where('id', $row->id)
                ->update(['chronic_diseases' => json_encode($parts)]);
        }

        Schema::table('medical_records', function (Blueprint $table) {
            $table->json('chronic_diseases')->nullable()->change();
            $table->text('other_conditions')->nullable()->after('chronic_diseases');
        });
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropColumn('other_conditions');
            $table->text('chronic_diseases')->nullable()->change();
        });
    }
};
