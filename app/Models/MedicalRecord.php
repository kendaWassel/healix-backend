<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    use HasFactory;
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'care_provider_id',
        'treatment_plan',
        'diagnosis',
        'chronic_diseases',
        'pre_existing_conditions',
        'other_conditions',
        'previous_surgeries',
        'allergies',
        'current_medications',
        'is_pregnant',
    ];
    protected $casts = [
        'is_pregnant' => 'boolean',
        // Stored as a JSON array of DrugCentral-standard condition names.
        'chronic_diseases' => 'array',
        // Stored as a JSON array of values from the frontend's
        // PRE_EXISTING_CONDITIONS picker — feeds lab analysis matching only,
        // see LabAnalysisService::PICKER_CONDITION_MAP.
        'pre_existing_conditions' => 'array',
        'allergies' => 'array',
        'current_medications' => 'array',
    ];
    

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function careProvider()
    {
        return $this->belongsTo(CareProvider::class);
    }

    public function uploads()
    {
        return $this->hasMany(Upload::class, 'medical_record_id');
    }
}
