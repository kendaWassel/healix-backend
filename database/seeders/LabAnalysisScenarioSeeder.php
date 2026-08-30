<?php

namespace Database\Seeders;

use App\Models\LabAnalysis;
use App\Models\User;
use Database\Seeders\Data\DemoScenarioData;
use Illuminate\Database\Seeder;

/**
 * There was no seeder at all for lab_analyses before this pass — the
 * patient's lab-results screen and the doctor/nurse "patient's lab
 * analyses" views were genuinely empty. 50 rows, biweekly over ~2 years —
 * plausible cadence for a patient under active monitoring for her seeded
 * chronic condition (Hypertensive disorder, PatientSeeder) — alternating
 * between a metabolic panel and a CBC panel so it isn't 50 identical rows.
 * Schema-accurate, seeded directly rather than via the live LabInsight AI
 * service, for the same reason AiAssistantDemoSeeder seeds its Assessment
 * rows directly — see that class's docblock.
 */
class LabAnalysisScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $patient = User::where('email', DemoScenarioData::PATIENT_EMAIL)->first()?->patient;
        if (! $patient) {
            $this->command->warn('LabAnalysisScenarioSeeder: run PatientSeeder first.');

            return;
        }

        $count = 50;
        for ($i = 0; $i < $count; $i++) {
            $analyzedAt = now()->subWeeks(($count - $i) * 2);
            $isMetabolic = $i % 2 === 0;

            if ($isMetabolic) {
                // Glucose/cholesterol drift mildly with the index so the
                // history shows real (small) variation, not 25 identical rows.
                $glucose = 95 + ($i % 6) * 4; // 95-115
                $cholesterol = 190 + ($i % 5) * 8; // 190-222
                $glucoseHigh = $glucose >= 100;
                $cholesterolHigh = $cholesterol >= 200;
                $abnormal = ($glucoseHigh ? 1 : 0) + ($cholesterolHigh ? 1 : 0);

                $data = [
                    'overall_severity' => $abnormal >= 2 ? 'moderate' : ($abnormal === 1 ? 'mild' : 'normal'),
                    'summary' => $abnormal > 0
                        ? 'ارتفاع طفيف بمؤشرات السكر و/أو الكوليسترول — يُنصح بمتابعة النظام الغذائي.'
                        : 'كل نتائج الفحص الأيضي ضمن المعدل الطبيعي.',
                    'total_tests_analyzed' => 6,
                    'abnormal_count' => $abnormal,
                    'normal_count' => 6 - $abnormal,
                    'test_results' => [
                        ['test_name' => 'Fasting Glucose', 'value' => $glucose, 'unit' => 'mg/dL', 'reference_range' => '70-99', 'status' => $glucoseHigh ? 'high' : 'normal'],
                        ['test_name' => 'Total Cholesterol', 'value' => $cholesterol, 'unit' => 'mg/dL', 'reference_range' => '<200', 'status' => $cholesterolHigh ? 'high' : 'normal'],
                        ['test_name' => 'HDL Cholesterol', 'value' => 52, 'unit' => 'mg/dL', 'reference_range' => '>40', 'status' => 'normal'],
                        ['test_name' => 'Creatinine', 'value' => 0.8, 'unit' => 'mg/dL', 'reference_range' => '0.6-1.2', 'status' => 'normal'],
                        ['test_name' => 'Sodium', 'value' => 140, 'unit' => 'mmol/L', 'reference_range' => '135-145', 'status' => 'normal'],
                        ['test_name' => 'Potassium', 'value' => 4.2, 'unit' => 'mmol/L', 'reference_range' => '3.5-5.0', 'status' => 'normal'],
                    ],
                    'conditions' => $abnormal > 0 ? ['Prediabetes risk'] : [],
                ];
            } else {
                $hemoglobin = round(12.8 + ($i % 5) * 0.2, 1); // 12.8-13.6
                $data = [
                    'overall_severity' => 'normal',
                    'summary' => 'تعداد الدم الكامل ضمن المعدل الطبيعي.',
                    'total_tests_analyzed' => 4,
                    'abnormal_count' => 0,
                    'normal_count' => 4,
                    'test_results' => [
                        ['test_name' => 'Hemoglobin', 'value' => $hemoglobin, 'unit' => 'g/dL', 'reference_range' => '12-16', 'status' => 'normal'],
                        ['test_name' => 'White Blood Cell Count', 'value' => 6.8, 'unit' => 'x10^3/uL', 'reference_range' => '4.5-11.0', 'status' => 'normal'],
                        ['test_name' => 'Platelet Count', 'value' => 260, 'unit' => 'x10^3/uL', 'reference_range' => '150-450', 'status' => 'normal'],
                        ['test_name' => 'TSH', 'value' => 2.1, 'unit' => 'mIU/L', 'reference_range' => '0.4-4.0', 'status' => 'normal'],
                    ],
                    'conditions' => [],
                ];
            }

            LabAnalysis::updateOrCreate(
                ['report_id' => 'seed-lab-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)],
                $data + [
                    'patient_id' => $patient->id,
                    'upload_id' => null,
                    'patient_info' => ['age' => 34, 'gender' => 'female'],
                    'disclaimer' => 'هاد التحليل مساعد آلي فقط، ولا يُغني عن مراجعة الطبيب المختص لتفسير النتائج واتخاذ القرار العلاجي المناسب.',
                    'analyzed_at' => $analyzedAt,
                ]
            );
        }
    }
}
