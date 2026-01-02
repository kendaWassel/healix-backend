<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


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
        'from',
        'to',
        'rating_avg',
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
    public function isOpen()
    {
        $currentTime = Carbon::now('Asia/Damascus');
        $from = Carbon::createFromFormat('H:i:s', $this->from, 'Asia/Damascus');
        $to = Carbon::createFromFormat('H:i:s', $this->to, 'Asia/Damascus');

        return $currentTime->between($from, $to);
    }

}
