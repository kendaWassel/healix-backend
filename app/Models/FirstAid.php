<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FirstAid extends Model
{
    protected $fillable = [
        'title',
        'description',
    ];

    protected $casts = [
        'created_at' => 'date',
        'updated_at' => 'datetime',
    ];
}
