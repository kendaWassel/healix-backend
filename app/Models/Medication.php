<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medication extends Model
{
    protected $fillable = [
        'name',
        'dosage',
    ];
    public function prescriptionItems()
    {
        return $this->hasMany(PrescriptionMedication::class);
    }
}
