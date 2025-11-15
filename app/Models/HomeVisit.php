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
        'careprovider_id',
        'service_type',
        'reason',
        'scheduled_at',
        'address',
        'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];
    protected $table = 'home_visits';

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function careProvider()
    {
        return $this->belongsTo(CareProvider::class, 'careprovider_id');
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
