<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class delivery extends Model
{
    use HasFactory;

    protected $table = 'deliveries';
    protected $primaryKey = 'delivery_id';
    protected $fillable = [
        'full_name', 'email', 'phone', 'photo', 'vehicle_type',
        'plate_number', 'bank_account', 'status', 'password'
    ];

    protected $hidden = ['password'];
}
