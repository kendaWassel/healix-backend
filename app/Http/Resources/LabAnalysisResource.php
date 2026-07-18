<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\LabAnalysis
 */
class LabAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report_id' => $this->report_id,
            'patient_id' => $this->patient_id,
            'overall_severity' => $this->overall_severity,
            'summary' => $this->summary,
            'total_tests_analyzed' => $this->total_tests_analyzed,
            'abnormal_count' => $this->abnormal_count,
            'normal_count' => $this->normal_count,
            'patient_info' => $this->patient_info,
            'test_results' => $this->test_results,
            'conditions' => $this->conditions,
            'disclaimer' => $this->disclaimer,
            'original_file_url' => $this->whenLoaded('upload', fn () => $this->upload?->url()),
            'pdf_url' => $request->getSchemeAndHttpHost() . "/api/lab/analyses/{$this->id}/pdf",
            'patient_pdf_url' => $request->getSchemeAndHttpHost() . "/api/lab/analyses/{$this->id}/patient-pdf",
            'analyzed_at' => $this->analyzed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
