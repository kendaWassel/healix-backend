<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrescriptionMedication extends Model
{
    protected $fillable = [
        'prescription_id',
        'medication_id',
        'boxes',
        'instructions',
    ];

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function medication()
    {
        return $this->belongsTo(Medication::class);
    }
}

