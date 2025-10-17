<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class careProvider extends Model
{
    use HasFactory;

    protected $table = 'careProvider';
    protected $primaryKey = 'careprovider_id';
    protected $fillable = [
        'full_name', 'type', 'age', 'certificate', 'email',
        'phone', 'photo', 'bank_account', 'session_fee', 'rating_avg', 'password'
    ];

    protected $hidden = ['password'];
}
