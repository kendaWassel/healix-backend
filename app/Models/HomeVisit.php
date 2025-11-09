<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HomeVisit extends Model
{
    use HasFactory;
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'careprovider_id',
        'scheduled_at',
        'service_type',
        'service',
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
}
