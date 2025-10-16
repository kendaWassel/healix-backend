<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    protected $fillable = [
        'doctor_image', 
        'working_hours', 
        'consultation_fee', 
        'bank_account', 
        'rating_avg', 
        'specialization_id', 
        'user_id'
    ];
    public function user() { 
        return $this->belongsTo(User::class); 
    }
    public function specialization() { 
        return $this->belongsTo(Specialization::class); 
    }
}
