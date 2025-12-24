<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pharmacist extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'pharmacy_name',
        'cr_number',
        'license_file_id',
        'address',
        'latitude',
        'longitude',
        'bank_account',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }
    public function orders(){
        return $this->hasMany(Order::class);
    }
    public function pharmacy(){
    return $this->belongsTo(Pharmacist::class);
    }
    public function isOpen()
    {
        $currentTime = now()->format('H:i:s');
        $pharmacy = $this; // Assuming this model represents the pharmacy

        return $currentTime >= $pharmacy->from && $currentTime <= $pharmacy->to;
    }

}
