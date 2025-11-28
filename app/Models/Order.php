<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'prescription_id',
        'pharmacy_id',
        'patient_id',
        'status',
    ];
    
    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }
    public function pharmacist()
    {
        return $this->belongsTo(Pharmacist::class);
    }
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
