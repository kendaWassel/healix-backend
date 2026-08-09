<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `allergies` and `current_medications` moved to JSON arrays (the app now
     * sends them as picker/tag lists, and the MedicalRecord model casts both to
     * arrays). Existing free-text values are converted so nothing is lost when
     * the array cast reads them back.
     */
    public function up(): void
    {
        foreach (['allergies', 'current_medications'] as $column) {
            foreach (DB::table('medical_records')->whereNotNull($column)->get(['id', $column]) as $row) {
                $raw = trim((string) $row->{$column});

                if ($raw === '') {
                    DB::table('medical_records')->where('id', $row->id)->update([$column => null]);
                    continue;
                }

                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    continue; // already JSON array
                }

                // A JSON-encoded scalar (e.g. "Aspirin") decodes to a string —
                // fall back to splitting the plain text either way.
                $text = is_string($decoded) ? $decoded : $raw;
                $parts = preg_split('/[,;\n\r]+/', $text) ?: [];
                $parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));

                DB::table('medical_records')->where('id', $row->id)
                    ->update([$column => json_encode($parts)]);
            }
        }

        Schema::table('medical_records', function (Blueprint $table) {
            $table->json('allergies')->nullable()->change();
            $table->json('current_medications')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->text('allergies')->nullable()->change();
            $table->text('current_medications')->nullable()->change();
        });
    }
};
