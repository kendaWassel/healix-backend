<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;          
use App\Models\Appointment;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CareProvider extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'care_provider_image_id',
        'license_file_id',
        'session_fee',
        'bank_account',
        'type', 
        'rating_avg'
    ];
    protected $table='care_providers';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function homeVisits()
    {
        return $this->hasMany(HomeVisit::class);
    }   

}
