<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = [
        'patient_id',
        'session_id',
        'status',
        'title',
        'started_at',
        'ended_at',
        'is_crisis',
        'severity',
        'red_flags',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_crisis' => 'boolean',
        'red_flags' => 'array',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function symptoms(): HasMany
    {
        return $this->hasMany(ConversationSymptom::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(Assessment::class);
    }
}
