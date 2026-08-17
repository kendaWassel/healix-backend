<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_BOOKED = 'booked';

    protected $fillable = [
        'conversation_id',
        'status',
        'triage',
        'recommended_specialty',
        'specialty_id',
        'specialty_code',
        'specialty_name_ar',
        'possible_diseases',
        'extracted_symptoms',
        'emergency_detected',
        'emergency_type',
        'risk_reason',
    ];

    protected $casts = [
        'possible_diseases' => 'array',
        'extracted_symptoms' => 'array',
        'emergency_detected' => 'boolean',
    ];
    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function specialization()
    {
        return $this->belongsTo(Specialization::class, 'specialty_id');
    }

    public function doctorSummary()
    {
        return $this->hasOne(DoctorSummary::class, 'assessment_id');
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class, 'assessment_id');
    }
}
