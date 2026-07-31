<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedColumns;
use Illuminate\Database\Eloquent\Model;

class FirstAid extends Model
{
    use HasLocalizedColumns;

    protected array $localizable = ['title', 'description'];

    protected $fillable = [
        'title',
        'title_ar',
        'description',
        'description_ar',
    ];

    protected $casts = [
        'created_at' => 'date',
        'updated_at' => 'datetime',
    ];
}
