<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrugInteractionCheck extends Model
{
    use HasFactory;

    public const TYPE_INTERACTION = 'interaction';
    public const TYPE_BATCH = 'batch';
    public const TYPE_SCREEN = 'screen';
    public const TYPE_ALLERGY = 'allergy';
    public const TYPE_PREGNANCY = 'pregnancy';

    protected $fillable = [
        'user_id',
        'patient_id',
        'check_type',
        'highest_severity',
        'interactions_found',
        'input',
        'result',
    ];

    protected $casts = [
        'input' => 'array',
        'result' => 'array',
        'interactions_found' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
