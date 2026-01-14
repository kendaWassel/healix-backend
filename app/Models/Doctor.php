<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;
    protected $fillable = [
        'doctor_image_id', 
        'from',
        'to', 
        'consultation_fee', 
        'rating_avg', 
        'certificate_file_id',
        'specialization_id', 
        'user_id',
        'gender'
    ];
    public function user() { 
        return $this->belongsTo(User::class); 
    }
    public function medicalRecordes() {
        return $this->hasMany(MedicalRecord::class);
    }
    public function specialization() { 
        return $this->belongsTo(Specialization::class); 
    }
    public function ratings(){
        return $this->hasMany(Rating::class);
    }
    public function homeVisits()
    {
        return $this->hasMany(HomeVisit::class);
    }
    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }
    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function certificateFile()
    {
        return $this->belongsTo(Upload::class, 'certificate_file_id');
    }

    public function doctorImage()
    {
        return $this->belongsTo(Upload::class, 'doctor_image_id');
    }

}
