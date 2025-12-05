<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'birth_date',
        'gender',
        'address',
        'latitude',
        'longitude',
    ];


    public function user()
    {
        return $this->belongsTo(User::class); 
    }

    public function medicalRecords()
    {
        return $this->hasOne(MedicalRecord::class);
    }
    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }
    public function homeVisits()
    {
        return $this->hasMany(HomeVisit::class);
    }
    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }
    public function consultations(){
        return $this->hasMany(  Consultation::class);
    }
}
