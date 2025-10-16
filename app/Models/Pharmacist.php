<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pharmacist extends Model
{
    protected $fillable = [
        'user_id',
        'pharmacy_name',
        'cr_number',
        'license_file',
        'address',
        'latitude',
        'longitude',
        'bank_account',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
