<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Prescription extends Model
{
    use HasFactory;
    protected $fillable = [
        'consultation_id',
        'doctor_id',
        'patient_id',
        'pharmacist_id',
        'diagnosis',
        'notes',
        'source',
        'status',
        'prescription_image_id',
        'total_price'
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
    public function medications()
    {
    return $this->hasMany(PrescriptionMedication::class);
    }
    
    public function prescriptionImage()
    {
        return $this->belongsTo(Upload::class, 'prescription_image_id');
    }
}
