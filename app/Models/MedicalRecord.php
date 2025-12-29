<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecord extends Model
{
    use HasFactory;
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'treatment_plan',
        'diagnosis',
        'chronic_diseases',
        'previous_surgeries',
        'allergies',
        'current_medications',
    ]; 
    protected $casts = [        
    ];
    

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function attachments()
    {
        return $this->belongsToMany(Upload::class, 'medical_record_uploads');
    }
}
