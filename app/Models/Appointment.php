<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'care_provider_id',
        'patient_id',
        'date',
        'time',
        'status',
        'notes', // sent by doctor
    ];

    public function careProvider()
    {
        return $this->belongsTo(CareProvider::class, 'care_provider_id');
    }

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }
}
