<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'attachments_id',
        'treatment_plan',
        'diagnosis',
        'chronic_diseases',
        'previous_surgeries',
        'allergies',
        'current_medications',
    ]; 
    protected $casts = [
        'attachments_id' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
    public function attachments(){
        return $this->hasMany(Upload::class);
    }
    


    
}
