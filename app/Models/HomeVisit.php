<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HomeVisit extends Model
{
    use HasFactory;
    protected $fillable = [
        'consultation_id',
        'patient_id',
        'doctor_id',
        'care_provider_id',
        'service_type',
        'reason',
        'scheduled_at',
        'address',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function careProvider()
    {
        return $this->belongsTo(CareProvider::class );
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }

}
