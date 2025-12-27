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
        return $this->hasManyThrough(
            Rating::class,
            User::class,
            'id',        // Foreign key on users table
            'user_id',   // Foreign key on ratings table
            'user_id',   // Local key on patients table
            'id'         // Local key on users table
        );
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
