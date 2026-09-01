<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Conversation extends Model
{
    protected $fillable = [
        'patient_id',
        'session_id',
        'healix_thread_id',
        'status',
        'title',
        'started_at',
        'ended_at',
        'is_crisis',
        'severity',
        'red_flags',
        'last_stage',
    ];

    /**
     * Always give a conversation a globally-unique Healix thread id at
     * creation time, regardless of which call site creates the row —
     * never derived from the auto-increment id, which is resettable
     * (see the 2026_08_29_075010 migration's own docstring for the bug
     * this fixes).
     */
    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation) {
            if (empty($conversation->healix_thread_id)) {
                $conversation->healix_thread_id = (string) Str::uuid();
            }
        });
    }

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
