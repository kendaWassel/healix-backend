<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'prescription_id',
        'pharmacy_id',
        'patient_id',
        'status',
    ];
    

}
