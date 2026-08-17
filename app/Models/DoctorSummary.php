<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorSummary extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';

    protected $table = 'doctor_summaries';
    protected $fillable = [
        'conversation_id',
        'assessment_id',
        'patient_id',
        'doctor_id',
        'summary',
        'status',
    ];

    public function assessment()
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }
    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }
}
