<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medication extends Model
{
    protected $fillable = [
        'name',
        'dosage',
        'duration',
        'notes',
        'price',
    ];
    public function prescriptions()
    {
        return $this->belongsToMany(Prescription::class);
    }
}
