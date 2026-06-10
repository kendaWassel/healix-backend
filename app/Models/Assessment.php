<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    protected $fillable = [
        'conversation_id',
        'triage',
        'recommended_specialty',
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
}
