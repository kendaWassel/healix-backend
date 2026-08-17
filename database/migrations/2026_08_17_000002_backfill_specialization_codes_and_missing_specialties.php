<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills a stable `code` for every specialization, and inserts the
 * specialties the AI service (disease_metadata.yaml / specialty_lookup.yaml /
 * its clinical-inference fallback) can recommend but that were never seeded
 * here — e.g. "Otolaryngology (ENT)", "Pulmonology", "Obstetrics and
 * Gynecology" (kept as its own row, separate from the existing "Gynecology",
 * since the two English strings differ and this migration never renames or
 * merges existing rows — same non-destructive rule as
 * 2026_07_21_000003_backfill_specialization_arabic_names.php).
 *
 * Only rows whose `code`/`name_ar` are still empty are touched, so an
 * admin-edited value is never overwritten and a specialty added later by an
 * admin is simply skipped.
 */
return new class extends Migration
{
    /** @var array<string, string> English name => code */
    private array $codes = [
        'Cardiology' => 'cardiology',
        'Neurology' => 'neurology',
        'Oncology' => 'oncology',
        'General Surgery' => 'general_surgery',
        'Psychiatry' => 'psychiatry',
        'Dermatology' => 'dermatology',
        'Urology' => 'urology',
        'Orthopedics' => 'orthopedics',
        'Pediatrics' => 'pediatrics',
        'Ophthalmology' => 'ophthalmology',
        'Gynecology' => 'gynecology',
    ];

    /** @var array<int, array{name: string, code: string, name_ar: string}> specialties missing entirely today */
    private array $missing = [
        ['name' => 'Gastroenterology', 'code' => 'gastroenterology', 'name_ar' => 'أمراض الجهاز الهضمي'],
        ['name' => 'General Medicine', 'code' => 'general_medicine', 'name_ar' => 'طب عام'],
        ['name' => 'Hematology', 'code' => 'hematology', 'name_ar' => 'أمراض الدم'],
        ['name' => 'Infectious Disease', 'code' => 'infectious_disease', 'name_ar' => 'الأمراض المُعدية'],
        ['name' => 'Otolaryngology (ENT)', 'code' => 'ent', 'name_ar' => 'أنف وأذن وحنجرة'],
        ['name' => 'Pulmonology', 'code' => 'pulmonology', 'name_ar' => 'أمراض الصدر والجهاز التنفسي'],
        ['name' => 'Rheumatology', 'code' => 'rheumatology', 'name_ar' => 'الروماتيزم والمفاصل'],
        ['name' => 'Family Medicine', 'code' => 'family_medicine', 'name_ar' => 'طب الأسرة'],
        ['name' => 'Endocrinology', 'code' => 'endocrinology', 'name_ar' => 'الغدد الصماء'],
        ['name' => 'Obstetrics and Gynecology', 'code' => 'obstetrics_gynecology', 'name_ar' => 'أمراض النساء والتوليد'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('specializations', 'code')) {
            return;
        }

        foreach ($this->codes as $english => $code) {
            DB::table('specializations')
                ->where('name', $english)
                ->where(function ($query) {
                    $query->whereNull('code')->orWhere('code', '');
                })
                ->update(['code' => $code]);
        }

        foreach ($this->missing as $entry) {
            $exists = DB::table('specializations')->where('name', $entry['name'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('specializations')->insert([
                'name' => $entry['name'],
                'name_ar' => $entry['name_ar'],
                'code' => $entry['code'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('specializations', 'code')) {
            return;
        }

        DB::table('specializations')
            ->whereIn('name', array_keys($this->codes))
            ->update(['code' => null]);

        DB::table('specializations')
            ->whereIn('name', array_column($this->missing, 'name'))
            ->delete();
    }
};
