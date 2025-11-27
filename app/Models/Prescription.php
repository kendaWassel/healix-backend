<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    protected $fillable = [
        'consultation_id',
        'doctor_id',
        'patient_id',
        'diagnosis',
        'notes',
        'source',
        'status',
        'prescription_image_id'
    ];
    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
    public function pharmacist()
    {
        return $this->belongsTo(Pharmacist::class);
    }
    public function items(){
         return $this->hasMany(PrescriptionMedication::class);
    }

}
