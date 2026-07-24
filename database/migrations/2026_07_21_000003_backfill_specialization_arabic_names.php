<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills Arabic names for the seeded specializations.
 *
 * Only rows whose English name matches a known specialty are touched, and only
 * when name_ar is still empty — so a translation edited by an admin is never
 * overwritten, and custom specialties added later are simply skipped.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $translations = [
        'Dermatology' => 'الأمراض الجلدية',
        'Cardiology' => 'أمراض القلب',
        'Orthopedics' => 'جراحة العظام',
        'Pediatrics' => 'طب الأطفال',
        'Neurology' => 'الأمراض العصبية',
        'Oncology' => 'الأورام',
        'Ophthalmology' => 'طب العيون',
        'General Surgery' => 'الجراحة العامة',
        'Psychiatry' => 'الطب النفسي',
        'Gynecology' => 'أمراض النساء والولادة',
        'Urology' => 'المسالك البولية',
        'Dentistry' => 'طب الأسنان',
        'ENT' => 'الأنف والأذن والحنجرة',
        'Internal Medicine' => 'الطب الباطني',
        'Endocrinology' => 'الغدد الصماء',
        'Gastroenterology' => 'الجهاز الهضمي',
        'Nephrology' => 'أمراض الكلى',
        'Pulmonology' => 'الأمراض الصدرية',
        'Rheumatology' => 'أمراض الروماتيزم',
        'Family Medicine' => 'طب الأسرة',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('specializations', 'name_ar')) {
            return;
        }

        foreach ($this->translations as $english => $arabic) {
            DB::table('specializations')
                ->where('name', $english)
                ->where(function ($query) {
                    $query->whereNull('name_ar')->orWhere('name_ar', '');
                })
                ->update(['name_ar' => $arabic]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('specializations', 'name_ar')) {
            return;
        }

        DB::table('specializations')
            ->whereIn('name', array_keys($this->translations))
            ->update(['name_ar' => null]);
    }
};
