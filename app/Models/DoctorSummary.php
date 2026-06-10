<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorSummary extends Model
{
    protected $table = 'doctor_summaries';
    protected $fillable = [
        'conversation_id',
        'assessment_id',
        'summary',
    ];

    public function assessment()
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }
    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
