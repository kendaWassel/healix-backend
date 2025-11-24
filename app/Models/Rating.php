<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $fillable = [
        'doctor_id',
        'patient_id',
        'order_id',
        'pharmacist_id',
        'consultation_id',
        'stars',
    ];
    
    public function doctor(){
        return $this->belongsTo(Doctor::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }
}
