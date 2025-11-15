<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    protected $fillable = [
        'consultation_id',
        'doctor_id',
        'patient_id',
        'pharmacist_id',
        'status',
        'notes',
        'prescription_file_id',
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
    public function medication(){
         return $this->belongsToMany(Medication::class);
    }

}
