<?php

namespace Tests\Unit;

use App\Http\Resources\LabAnalysisResource;
use App\Models\LabAnalysis;
use App\Models\Upload;
use Illuminate\Http\Request;
use Tests\TestCase;

class LabAnalysisResourceTest extends TestCase
{
    public function test_it_builds_original_file_url_for_uploaded_lab_report(): void
    {
        $upload = new Upload([
            'file_path' => 'lab_reports/originals/test-report.pdf',
        ]);

        $analysis = new LabAnalysis([
            'id' => 12,
            'report_id' => 'lab-123',
            'patient_id' => 7,
            'overall_severity' => 'high',
            'summary' => 'Summary',
            'total_tests_analyzed' => 5,
            'abnormal_count' => 2,
            'normal_count' => 3,
            'patient_info' => ['age' => 29],
            'test_results' => [],
            'conditions' => [],
            'disclaimer' => 'Disclaimer',
            'analyzed_at' => now(),
            'created_at' => now(),
        ]);

        $analysis->setRelation('upload', $upload);

        $payload = (new LabAnalysisResource($analysis))->toArray(Request::create('/api/lab/analyze'));

        $this->assertSame(asset('/storage/lab_reports/originals/test-report.pdf'), $payload['original_file_url']);
    }
}
