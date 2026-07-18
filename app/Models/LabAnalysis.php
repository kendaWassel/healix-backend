<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabAnalysis extends Model
{
    use HasFactory;

    protected $table = 'lab_analyses';

    protected $fillable = [
        'patient_id',
        'upload_id',
        'report_id',
        'overall_severity',
        'summary',
        'total_tests_analyzed',
        'abnormal_count',
        'normal_count',
        'patient_info',
        'test_results',
        'conditions',
        'disclaimer',
        'pdf_path',
        'patient_pdf_path',
        'analyzed_at',
    ];

    protected $casts = [
        'patient_info' => 'array',
        'test_results' => 'array',
        'conditions' => 'array',
        'analyzed_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}
