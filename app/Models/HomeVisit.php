<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeVisit extends Model
{
    protected $primaryKey = 'visit_id';

    protected $fillable = [
        'patient_id',
        'requested_by_doctor_id',
        'careprovider_id',
        'scheduled_at',
        'start_time',
        'end_time',
        'service_type',
        'status',
        'fee',
    ];

    public function patient()
{
    return $this->belongsTo(User::class, 'patient_id');
}

    public function careProvider()
    {
        return $this->belongsTo(\App\Models\CareProvider::class, 'careprovider_id');
    }

    public function doctor()
    {
        return $this->belongsTo(\App\Models\Doctor::class, 'requested_by_doctor_id');
    }
}
