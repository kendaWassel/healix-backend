<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Medication extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'dosage',
    ];
    public function prescriptionItems()
    {
        return $this->hasMany(PrescriptionMedication::class);
    }
    public function orderItems()
    {
        return $this->hasMany(OrderMedication::class);
    }
}
