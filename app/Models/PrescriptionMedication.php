<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PrescriptionMedication extends Model
{
    use HasFactory;
    protected $fillable = [
        'prescription_id',
        'medication_id',
        'medicine_name',
        'quantity',
        'price',
        'subtotal',
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

