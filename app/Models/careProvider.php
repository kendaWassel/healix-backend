<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;          
use App\Models\Appointment;   

class CareProvider extends Model
{
    protected $fillable = [
        'user_id',
        'care_provider_image_id',
        'license_file_id',
        'session_fee',
        'bank_account',
        'type', 
        'rating_avg'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
