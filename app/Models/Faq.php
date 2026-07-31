<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory, HasLocalizedColumns;

    protected array $localizable = ['question', 'answer'];

    protected $fillable = [
        'question',
        'question_ar',
        'answer',
        'answer_ar',
    ];

    protected $casts = [
        'created_at' => 'date',
        'updated_at' => 'datetime',
    ];
}
